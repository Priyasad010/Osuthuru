"""Holds the loaded data and model, refreshed every 15 minutes."""
import logging
import threading
import time

from . import data
from .recommender import Recommender

REFRESH_SECONDS = 15 * 60
log = logging.getLogger("osuthuru-ml")


class ModelStore:
    def __init__(self, engine=None, clock=time.monotonic):
        self._engine = engine
        self._clock = clock
        self._lock = threading.Lock()
        self._loaded_at = None
        self._data = None
        self._model = None

    @property
    def engine(self):
        if self._engine is None:
            self._engine = data.make_engine()
        return self._engine

    def current(self):
        """(dataset, recommender). A failed refresh keeps serving the previous copy."""
        with self._lock:
            now = self._clock()
            if self._loaded_at is None or now - self._loaded_at >= REFRESH_SECONDS:
                try:
                    loaded = self.load_dataset()
                    self._data, self._model, self._loaded_at = loaded, Recommender(loaded), now
                except Exception:
                    if self._data is None:
                        raise
                    log.exception("Refresh failed; using the previous data")
                    self._loaded_at = now
            return self._data, self._model

    def load_dataset(self):
        return data.load_dataset(self.engine)

    def load_profile(self, user_id: int):
        return data.load_profile(self.engine, user_id)

    def load_stock(self, product_id: int):
        return data.load_stock(self.engine, product_id)
