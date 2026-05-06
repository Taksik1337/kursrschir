# app/domain/models.py
from typing import Optional, Dict, Any

class User:
    def __init__(self, username: str, password_hash: str, id: Optional[int] = None):
        self.id = id
        self.username = username
        self.password_hash = password_hash

class SyncData:
    def __init__(self, user_id: int, data: Dict[str, Any], last_updated: float, device_id: str):
        self.user_id = user_id
        self.data = data
        self.last_updated = last_updated
        self.device_id = device_id