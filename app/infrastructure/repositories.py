from typing import Optional
from sqlalchemy.orm import Session
from sqlalchemy.orm.attributes import flag_modified
from app.domain.models import SyncData, User
from app.interfaces.repositories import SyncRepository, UserRepository
from app.infrastructure.orm import SyncDataORM, UserORM


class SqlAlchemySyncRepository(SyncRepository):
    def __init__(self, db: Session):
        self.db = db

    def get_data(self, user_id: int) -> Optional[SyncData]:
        record = self.db.query(SyncDataORM).filter(SyncDataORM.user_id == user_id).first()
        if record:
            return SyncData(
                user_id=record.user_id,
                data=record.data_payload,
                last_updated=record.last_updated,
                device_id=record.last_device_id
            )
        return None

    def save_data(self, data: SyncData):
        record = self.db.query(SyncDataORM).filter(SyncDataORM.user_id == data.user_id).first()
        if not record:
            record = SyncDataORM(user_id=data.user_id)
            self.db.add(record)

        record.data_payload = data.data
        record.last_updated = data.last_updated
        record.last_device_id = data.device_id

        # Сообщаем базе, что JSON изменился
        flag_modified(record, "data_payload")
        self.db.commit()


class SqlAlchemyUserRepository(UserRepository):
    def __init__(self, db: Session):
        self.db = db

    def get_by_username(self, username: str) -> Optional[User]:
        record = self.db.query(UserORM).filter(UserORM.username == username).first()
        if record:
            return User(id=record.id, username=record.username, password_hash=record.password_hash)
        return None

    def create(self, user: User) -> User:
        db_user = UserORM(username=user.username, password_hash=user.password_hash)
        self.db.add(db_user)
        self.db.commit()
        self.db.refresh(db_user)
        user.id = db_user.id
        return user