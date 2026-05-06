# app/use_cases/sync_service.py
from typing import Optional
from app.domain.models import SyncData
from app.interfaces.repositories import SyncRepository


class SyncService:
    def __init__(self, repository: SyncRepository):
        self.repository = repository

    def sync_user_data(self, incoming_data: SyncData) -> SyncData:
        current_data = self.repository.get_data(incoming_data.user_id)

        # Если это просто запрос данных (timestamp 0), отдаем что есть в БД
        if incoming_data.last_updated == 0:
            return current_data if current_data else incoming_data

        # Если данных в базе нет или пришедшие данные новее — сохраняем их
        if not current_data or incoming_data.last_updated > current_data.last_updated:
            self.repository.save_data(incoming_data)
            return incoming_data

        # В остальных случаях (если на сервере данные свежее) возвращаем базу
        return current_data

    def get_current_state(self, user_id: int) -> Optional[SyncData]:
        return self.repository.get_data(user_id)