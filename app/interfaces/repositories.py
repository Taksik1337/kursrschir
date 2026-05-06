# app/interfaces/repositories.py
from abc import ABC, abstractmethod
from typing import Optional
from app.domain.models import User, SyncData

class UserRepository(ABC):
    @abstractmethod
    def get_by_username(self, username: str) -> Optional[User]:
        pass

    @abstractmethod
    def create(self, user: User) -> User:
        pass

class SyncRepository(ABC):
    @abstractmethod
    def get_data(self, user_id: int) -> Optional[SyncData]:
        pass

    @abstractmethod
    def save_data(self, data: SyncData):
        pass