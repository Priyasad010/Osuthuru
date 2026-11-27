"""Suwoda HerbHub ML service. Internal only: the PHP application is its sole client."""
import logging
from datetime import datetime
from zoneinfo import ZoneInfo

from fastapi import Depends, FastAPI, Query, Request
from fastapi.responses import JSONResponse
from sqlalchemy.exc import SQLAlchemyError

from . import forecast as forecasting
from .data import UserProfile
from .store import ModelStore

log = logging.getLogger("suwoda-ml")

app = FastAPI(title="Suwoda HerbHub ML Service", version="2.0.0")
app.state.store = ModelStore()


def get_store(request: Request) -> ModelStore:
    return request.app.state.store


def today():
    return datetime.now(ZoneInfo("Asia/Colombo")).date()


def ok(data):
    return {"ok": True, "data": data}


def error(message: str, status: int):
    return JSONResponse({"ok": False, "error": message}, status_code=status)


@app.exception_handler(SQLAlchemyError)
def database_error(_: Request, exc: SQLAlchemyError):
    log.error("Database error: %s", type(exc).__name__)
    return error("Data is not available", 503)


@app.get("/health")
def health_check():
    return {"ok": True, "service": "suwoda-ml"}


@app.get("/recommend/user/{user_id}")
def recommend_user(user_id: int, limit: int = Query(8, ge=1, le=24), store: ModelStore = Depends(get_store)):
    _, model = store.current()
    # User 0 is a guest
    profile = store.load_profile(user_id) if user_id > 0 else UserProfile()
    return ok({
        "product_ids": model.for_user(profile, limit),
        "cold_start": not profile.purchased,
    })


@app.get("/recommend/product/{product_id}")
def recommend_product(product_id: int, limit: int = Query(6, ge=1, le=24), store: ModelStore = Depends(get_store)):
    _, model = store.current()
    result = model.similar(product_id, limit)
    return ok(result) if result is not None else error("Unknown product", 404)


@app.get("/forecast/sales")
def forecast_sales(horizon: int = Query(30, ge=1, le=90), store: ModelStore = Depends(get_store)):
    data, _ = store.current()
    day = today()
    series = forecasting.daily_series(data.sales, "subtotal", day)
    return ok(forecasting.forecast(series, horizon, day))


@app.get("/forecast/stock")
def forecast_stock(store: ModelStore = Depends(get_store)):
    data, _ = store.current()
    return ok({"products": forecasting.recent_demand(data.sales, today())})


@app.get("/forecast/product/{product_id}")
def forecast_product(product_id: int, store: ModelStore = Depends(get_store)):
    data, _ = store.current()
    stock = store.load_stock(product_id)
    if stock is None:
        return error("Unknown product", 404)
    day = today()
    sales = data.sales[data.sales["product_id"] == product_id]
    series = forecasting.daily_series(sales, "quantity", day)
    return ok({"product_id": product_id, **forecasting.stock_outlook(series, stock, day)})
