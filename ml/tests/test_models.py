from datetime import date, datetime, timedelta

import numpy as np
import pandas as pd
import pytest
from fastapi.testclient import TestClient
from sqlalchemy.exc import OperationalError

from app import evaluate, forecast
from app.data import Dataset, UserProfile
from app.main import app
from app.recommender import Recommender
from app.store import REFRESH_SECONDS, ModelStore

TODAY = date(2026, 11, 27)


def dataset(orders=None) -> Dataset:
    products = pd.DataFrame(
        [
            (1, 10, 50, "neem oil skin"),
            (2, 10, 50, "sesame oil massage"),
            (3, 20, 50, "gotukola tea leaves"),
            (4, 20, 50, "ranawara tea flowers"),
            (5, 30, 50, "baby soap coconut"),
            (6, 30, 0, "neem soap skin"),  # out of stock
        ],
        columns=["product_id", "category_id", "stock", "text"],
    )
    if orders is None:
        orders = [(1, 100, [5]), (2, 101, [5]), (3, 102, [5, 1]), (4, 103, [3, 4]), (5, 104, [3, 4])]
    rows = [
        (order_id, user_id, datetime(2026, 11, 1) + timedelta(days=order_id), product, 1, 100.0)
        for order_id, user_id, basket in orders
        for product in basket
    ]
    sales = pd.DataFrame(rows, columns=["order_id", "user_id", "order_date", "product_id", "quantity", "subtotal"])
    ratings = pd.DataFrame(columns=["product_id", "rating"])
    dosha = pd.DataFrame([(2, "vata", 3), (4, "pitta", 3)], columns=["product_id", "dosha", "strength"])
    return Dataset(products, sales, ratings, dosha)


# ---------------------------------------------------------------- recommender

def test_new_customer_gets_popular_products():
    result = Recommender(dataset()).for_user(UserProfile(), 3)
    assert result[0] == 5


def test_history_changes_the_recommendations():
    model = Recommender(dataset())
    new = model.for_user(UserProfile(), 3)
    returning = model.for_user(UserProfile(purchased=[3]), 3)
    assert new != returning
    assert returning[0] == 4  # bought together with 3, and also a tea
    assert 3 not in returning


def test_chosen_category_ranks_higher():
    model = Recommender(dataset())
    assert model.for_user(UserProfile(categories={10}), 2) == [1, 2]


def test_saved_dosha_ranks_matching_products_higher():
    model = Recommender(dataset())
    vata = model.for_user(UserProfile(dosha={"vata": 80, "pitta": 10, "kapha": 10}), 6)
    pitta = model.for_user(UserProfile(dosha={"vata": 10, "pitta": 80, "kapha": 10}), 6)
    assert vata[0] == 2
    assert pitta[0] == 4


def test_out_of_stock_is_never_recommended():
    model = Recommender(dataset())
    assert 6 not in model.for_user(UserProfile(purchased=[1]), 10)
    assert 6 not in model.similar(1)["similar"]


def test_bought_products_only_fill_the_list():
    result = Recommender(dataset()).for_user(UserProfile(purchased=[1, 2]), 5)
    assert result[-2:] and set(result[-2:]) == {1, 2}


def test_similar_products_and_also_bought():
    result = Recommender(dataset()).similar(3)
    assert result["similar"][0] == 4
    assert result["also_bought"] == [4]
    assert 3 not in result["similar"] + result["also_bought"]
    assert Recommender(dataset()).similar(999) is None


def test_empty_shop_does_not_fail():
    empty = Dataset(
        pd.DataFrame(columns=["product_id", "category_id", "stock", "text"]),
        pd.DataFrame(columns=["order_id", "user_id", "order_date", "product_id", "quantity", "subtotal"]),
        pd.DataFrame(columns=["product_id", "rating"]),
        pd.DataFrame(columns=["product_id", "dosha", "strength"]),
    )
    assert Recommender(empty).for_user(UserProfile(), 5) == []


# ---------------------------------------------------------------- forecast

def check_days(result, horizon):
    assert len(result["days"]) == horizon
    assert result["days"][0]["date"] == TODAY.isoformat()
    for day in result["days"]:
        assert 0 <= day["lower"] <= day["value"] <= day["upper"]


@pytest.mark.parametrize("length", [0, 1, 10, 29])
def test_too_little_data_uses_a_moving_average(length):
    result = forecast.forecast(np.full(length, 4.0), 30, TODAY)
    assert result["method"] == "moving_average"
    assert result["history_days"] == length
    check_days(result, 30)


def test_enough_data_uses_holt_winters():
    series = np.array([100 + (40 if i % 7 in (5, 6) else 0) + i % 3 for i in range(90)], dtype=float)
    result = forecast.forecast(series, 30, TODAY)
    assert result["method"] == "holt_winters"
    check_days(result, 30)
    assert 80 < result["days"][0]["value"] < 180


def test_no_sales_at_all_forecasts_zero():
    result = forecast.forecast(np.zeros(60), 30, TODAY)
    assert result["method"] == "moving_average"
    assert all(d["value"] == 0 for d in result["days"])


def test_daily_series_fills_gaps_and_ignores_today():
    sales = pd.DataFrame(
        {"order_date": [datetime(2026, 11, 24, 9), datetime(2026, 11, 24, 18), datetime(2026, 11, 27, 8)], "quantity": [1, 2, 9]}
    )
    assert forecast.daily_series(sales, "quantity", TODAY).tolist() == [3.0, 0.0, 0.0]


def test_stock_outlook():
    assert forecast.stock_outlook(np.full(10, 2.0), 20, TODAY)["days_until_stockout"] == 10
    assert forecast.stock_outlook(np.zeros(10), 20, TODAY)["days_until_stockout"] is None
    assert forecast.stock_outlook(np.full(10, 2.0), 0, TODAY)["days_until_stockout"] == 0


# ---------------------------------------------------------------- API

class FakeStore(ModelStore):
    def __init__(self, fail=False):
        super().__init__(engine=object())
        self.fail = fail

    def load_dataset(self):
        if self.fail:
            raise OperationalError("select", {}, Exception("down"))
        return dataset()

    def load_profile(self, user_id):
        return UserProfile(purchased=[3]) if user_id == 7 else UserProfile()

    def load_stock(self, product_id):
        return 40 if product_id <= 6 else None


@pytest.fixture
def client():
    original = app.state.store
    app.state.store = FakeStore()
    yield TestClient(app)
    app.state.store = original


def test_recommend_endpoints(client):
    guest = client.get("/recommend/user/0?limit=3").json()
    member = client.get("/recommend/user/7?limit=3").json()
    assert guest["ok"] and guest["data"]["cold_start"]
    assert not member["data"]["cold_start"]
    assert guest["data"]["product_ids"] != member["data"]["product_ids"]
    assert client.get("/recommend/product/3").json()["data"]["similar"]
    assert client.get("/recommend/product/999").status_code == 404
    assert client.get("/recommend/user/1?limit=500").status_code == 422


def test_forecast_endpoints(client):
    sales = client.get("/forecast/sales?horizon=30").json()
    assert sales["ok"] and len(sales["data"]["days"]) == 30
    product = client.get("/forecast/product/5").json()["data"]
    assert product["stock"] == 40 and "daily_demand" in product
    assert client.get("/forecast/product/99").status_code == 404


def test_database_failure_is_reported_as_unavailable():
    original = app.state.store
    app.state.store = FakeStore(fail=True)
    try:
        response = TestClient(app).get("/recommend/user/0")
        assert response.status_code == 503
        assert response.json() == {"ok": False, "error": "Data is not available"}
    finally:
        app.state.store = original


def test_data_is_refreshed_after_fifteen_minutes_and_survives_failure():
    now = [0.0]
    store = FakeStore()
    store._clock = lambda: now[0]
    first = store.current()[1]
    now[0] = REFRESH_SECONDS - 1
    assert store.current()[1] is first
    now[0] = REFRESH_SECONDS
    second = store.current()[1]
    assert second is not first
    store.fail = True
    now[0] = REFRESH_SECONDS * 2
    assert store.current()[1] is second


# ---------------------------------------------------------------- evaluation

def test_evaluation_hides_one_product_and_writes_numbers():
    orders = [(1, 100, [3]), (2, 100, [4]), (3, 101, [3]), (4, 101, [4]), (5, 102, [3]), (6, 102, [4]), (7, 103, [5])]
    data = dataset(orders)
    train, held_out = evaluate.split(data)
    assert held_out == {100: 4, 101: 4, 102: 4}
    assert not ((train.sales["user_id"] == 100) & (train.sales["product_id"] == 4)).any()

    result = evaluate.evaluate(data, {})
    assert result["users"] == 3
    for scores in result["scores"].values():
        assert 0 <= scores["precision"] <= 0.2 and 0 <= scores["recall"] <= 1
    text = evaluate.report(result, "suwoda_eval", data)
    assert "Synthetic data" in text and "Popularity baseline" in text and "Hybrid" in text
