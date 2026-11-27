"""Hybrid recommender: content, co-purchase, popularity, category and dosha."""
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

from .data import DOSHAS, Dataset, UserProfile

# Weight of each signal for a customer with order history
WEIGHTS = {"content": 0.25, "copurchase": 0.30, "popularity": 0.15, "category": 0.15, "dosha": 0.15}
# Cold start: nothing bought yet, so only popularity and what the customer chose.
# A chosen category outweighs popularity.
COLD_WEIGHTS = {"popularity": 0.30, "category": 0.40, "dosha": 0.30}
# Reviews needed before an average rating outweighs the overall average
RATING_PRIOR = 5


def _scaled(values: np.ndarray) -> np.ndarray:
    """Scale to 0..1; all zeros stay zero."""
    top = values.max() if values.size else 0
    return values / top if top > 0 else np.zeros_like(values, dtype=float)


class Recommender:
    def __init__(self, data: Dataset):
        products = data.products.reset_index(drop=True)
        self.ids = [int(p) for p in products["product_id"]]
        self.index = {p: i for i, p in enumerate(self.ids)}
        self.category = [None if c != c or c is None else int(c) for c in products["category_id"]]
        self.in_stock = np.array([s > 0 for s in products["stock"]], dtype=bool)
        n = len(self.ids)

        self.content = np.zeros((n, n))
        if n and any(t.strip() for t in products["text"]):
            try:
                matrix = TfidfVectorizer(stop_words="english").fit_transform(products["text"])
                self.content = cosine_similarity(matrix)
            except ValueError:  # no usable words
                pass

        # Item-to-item co-purchase: orders containing both, relative to each item's orders
        self.copurchase = np.zeros((n, n))
        sales = data.sales[data.sales["product_id"].isin(self.index)]
        for _, basket in sales.groupby("order_id")["product_id"]:
            rows = sorted({self.index[int(p)] for p in basket})
            for a in rows:
                self.copurchase[a, rows] += 1
        counts = np.diag(self.copurchase).copy()
        norm = np.sqrt(np.outer(counts, counts))
        self.copurchase = np.divide(self.copurchase, norm, out=np.zeros_like(self.copurchase), where=norm > 0)

        units = np.zeros(n)
        for product_id, quantity in sales.groupby("product_id")["quantity"].sum().items():
            units[self.index[int(product_id)]] = float(quantity)
        rating = np.zeros(n)
        if not data.ratings.empty:
            overall = float(data.ratings["rating"].mean())
            grouped = data.ratings.groupby("product_id")["rating"].agg(["sum", "count"])
            rating[:] = overall / 5
            for product_id, row in grouped.iterrows():
                if int(product_id) in self.index:
                    value = (float(row["sum"]) + RATING_PRIOR * overall) / (float(row["count"]) + RATING_PRIOR)
                    rating[self.index[int(product_id)]] = value / 5
        self.popularity = 0.8 * _scaled(units) + 0.2 * rating

        self.dosha = np.zeros((n, len(DOSHAS)))
        for row in data.product_dosha.itertuples():
            if int(row.product_id) in self.index:
                self.dosha[self.index[int(row.product_id)], DOSHAS.index(row.dosha)] = float(row.strength)

    def user_scores(self, profile: UserProfile) -> np.ndarray:
        n = len(self.ids)
        category = np.array([1.0 if c in profile.categories else 0.0 for c in self.category])
        dosha = np.zeros(n)
        if profile.dosha:
            dosha = _scaled(self.dosha @ np.array([profile.dosha.get(d, 0) for d in DOSHAS], dtype=float))

        history = [self.index[p] for p in profile.purchased if p in self.index]
        if not history:
            parts = {"popularity": self.popularity, "category": category, "dosha": dosha}
            return sum(COLD_WEIGHTS[k] * v for k, v in parts.items()) if n else np.zeros(0)

        parts = {
            "content": _scaled(self.content[history].mean(axis=0)),
            "copurchase": _scaled(self.copurchase[history].mean(axis=0)),
            "popularity": self.popularity,
            "category": category,
            "dosha": dosha,
        }
        return sum(WEIGHTS[k] * v for k, v in parts.items())

    def _top(self, scores: np.ndarray, allowed: np.ndarray, limit: int) -> list[int]:
        # Highest score first; the product id breaks ties so results are repeatable
        order = sorted(np.flatnonzero(allowed), key=lambda i: (-scores[i], self.ids[i]))
        return [self.ids[i] for i in order[:limit]]

    def for_user(self, profile: UserProfile, limit: int = 8) -> list[int]:
        """In-stock products, unbought ones first; bought ones only fill the list."""
        scores = self.user_scores(profile)
        bought = np.array([p in set(profile.purchased) for p in self.ids], dtype=bool)
        result = self._top(scores, self.in_stock & ~bought, limit)
        if len(result) < limit:
            result += self._top(scores, self.in_stock & bought, limit - len(result))
        return result

    def similar(self, product_id: int, limit: int = 6) -> dict | None:
        if product_id not in self.index:
            return None
        row = self.index[product_id]
        others = self.in_stock.copy()
        others[row] = False

        # A little popularity orders products with equal similarity
        content = self.content[row] + 0.001 * self.popularity
        together = self.copurchase[row]
        return {
            "similar": self._top(content, others & (self.content[row] > 0), limit),
            "also_bought": self._top(together, others & (together > 0), limit),
        }
