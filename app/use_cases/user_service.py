# app/use_cases/user_service.py
from typing import Optional
from app.domain.models import User
from app.interfaces.repositories import UserRepository
from app.infrastructure.security import get_password_hash, verify_password, create_access_token


class UserService:
    def __init__(self, repository: UserRepository):
        self.repository = repository

    def register_user(self, username: str, password: str) -> User:
        existing_user = self.repository.get_by_username(username)
        if existing_user:
            raise ValueError("Username already registered")

        hashed_password = get_password_hash(password)
        new_user = User(username=username, password_hash=hashed_password)
        return self.repository.create(new_user)

    def authenticate_user(self, username: str, password: str) -> Optional[dict]:
        user = self.repository.get_by_username(username)
        if not user:
            return None
        if not verify_password(password, user.password_hash):
            return None

        # Если пароль верный, создаем токен
        access_token = create_access_token(data={"sub": user.username, "uid": user.id})
        return {"access_token": access_token, "token_type": "bearer"}