"""Read the shop data the models need, using the read-only database user.

The user may read only these tables and columns (see bin/grant_ml_user.php), so
every query names its columns. No names, addresses or email addresses are read.
"""
import os
from dataclasses import dataclass, field

import pandas as pd
from sqlalchemy import create_engine, text
from sqlalchemy.engine import URL

# An order counts as a sale unless it was cancelled or is still waiting for payment
SALE_CONDITION = "o.status <> 'Cancelled' AND (o.payment_status = 'paid' OR o.status <> 'Pending' OR o.payment_method = 'cod')"

DOSHAS = ("vata", "pitta", "kapha")


@dataclass
class UserProfile:
    """What is known about one customer."""

    purchased: list = field(default_factory=list)  # product ids, repeats allowed
    categories: set = field(default_factory=set)
    dosha: dict | None = None  # percentages by dosha


@dataclass
class Dataset:
    # product_id, category_id, stock, text
    products: pd.DataFrame
    # order_id, user_id, order_date, product_id, quantity, subtotal
    sales: pd.DataFrame
    # product_id, rating
    ratings: pd.DataFrame
    # product_id, dosha, strength
    product_dosha: pd.DataFrame


def make_engine(database: str | None = None):
    url = URL.create(
        "mysql+pymysql",
        username=os.getenv("DB_ML_RO_USER", "ml_ro"),
        password=os.getenv("DB_ML_RO_PASS", "ml_ro_secret"),
        host=os.getenv("DB_HOST", "db"),
        port=int(os.getenv("DB_PORT", "3306")),
        database=database or os.getenv("DB_NAME", "osuthuru"),
        query={"charset": "utf8mb4"},
    )
    return create_engine(url, pool_pre_ping=True, pool_recycle=600, connect_args={"connect_timeout": 2})


def _frame(connection, sql: str, **params) -> pd.DataFrame:
    result = connection.execute(text(sql), params)
    return pd.DataFrame(result.fetchall(), columns=list(result.keys()))


def load_dataset(engine) -> Dataset:
    with engine.connect() as db:
        products = _frame(db, """
            SELECT p.product_id, p.category_id, p.quantity AS stock,
                   p.product_name, p.description, c.name AS category_name
            FROM product p LEFT JOIN category c ON c.category_id = p.category_id
        """)
        herbs = _frame(db, """
            SELECT ph.product_id, h.name, h.scientific_name, h.uses, hc.name_en AS concern
            FROM product_herb ph
            JOIN herb h ON h.herb_id = ph.herb_id
            LEFT JOIN herb_concern x ON x.herb_id = h.herb_id
            LEFT JOIN health_concern hc ON hc.concern_id = x.concern_id
        """)
        product_dosha = _frame(db, "SELECT product_id, dosha, strength FROM product_dosha")
        sales = _frame(db, f"""
            SELECT o.order_id, o.user_id, o.order_date, i.product_id, i.quantity, i.subtotal
            FROM orders o JOIN order_item i ON i.order_id = o.order_id
            WHERE {SALE_CONDITION}
        """)
        ratings = _frame(db, "SELECT product_id, rating FROM review WHERE rating IS NOT NULL")

    return Dataset(
        products=build_product_text(products, herbs, product_dosha),
        sales=sales,
        ratings=ratings,
        product_dosha=product_dosha,
    )


def build_product_text(products: pd.DataFrame, herbs: pd.DataFrame, product_dosha: pd.DataFrame) -> pd.DataFrame:
    """One text per product: name, description, category, herbs, health topics and dosha."""
    words: dict[int, list[str]] = {}
    for row in products.itertuples():
        words[row.product_id] = [str(v) for v in (row.product_name, row.description, row.category_name) if v]
    for column in ("name", "scientific_name", "uses", "concern"):
        for product_id, value in herbs[["product_id", column]].dropna().drop_duplicates().itertuples(index=False):
            if product_id in words:
                words[product_id].append(str(value))
    for row in product_dosha.itertuples():
        if row.product_id in words:
            words[row.product_id].append(f"dosha_{row.dosha}")

    out = products[["product_id", "category_id", "stock"]].copy()
    out["text"] = [" ".join(words[p]) for p in out["product_id"]]
    return out


def load_profile(engine, user_id: int) -> UserProfile:
    """Read fresh on every request, so new orders and choices apply at once."""
    with engine.connect() as db:
        purchased = _frame(db, f"""
            SELECT i.product_id FROM orders o JOIN order_item i ON i.order_id = o.order_id
            WHERE o.user_id = :user AND {SALE_CONDITION}
        """, user=user_id)
        categories = _frame(db, "SELECT category_id FROM user_category WHERE user_id = :user", user=user_id)
        dosha = _frame(db, """
            SELECT vata, pitta, kapha FROM quiz_result WHERE user_id = :user ORDER BY result_id DESC LIMIT 1
        """, user=user_id)

    return UserProfile(
        purchased=[int(p) for p in purchased["product_id"]],
        categories={int(c) for c in categories["category_id"].dropna()},
        dosha=None if dosha.empty else {d: int(dosha.iloc[0][d]) for d in DOSHAS},
    )


def load_stock(engine, product_id: int) -> int | None:
    with engine.connect() as db:
        value = db.execute(text("SELECT quantity FROM product WHERE product_id = :id"), {"id": product_id}).scalar()
    return None if value is None else int(value)
