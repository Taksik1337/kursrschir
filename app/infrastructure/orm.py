# app/infrastructure/orm.py
from sqlalchemy import Column, Integer, String, Float, JSON
from .database import Base

class UserORM(Base):
    __tablename__ = "users"
    id = Column(Integer, primary_key=True, index=True)
    username = Column(String, unique=True, index=True)
    password_hash = Column(String)

class SyncDataORM(Base):
    __tablename__ = "sync_data"
    user_id = Column(Integer, primary_key=True, index=True)
    data_payload = Column(JSON)
    last_updated = Column(Float)
    last_device_id = Column(String)