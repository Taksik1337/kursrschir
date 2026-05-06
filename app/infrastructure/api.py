from fastapi import APIRouter, Depends, HTTPException, status
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm
from pydantic import BaseModel
from typing import Dict, Any, Optional
from sqlalchemy.orm import Session
from jose import JWTError, jwt

from app.infrastructure.database import get_db
from app.infrastructure.repositories import SqlAlchemySyncRepository, SqlAlchemyUserRepository
from app.infrastructure.security import SECRET_KEY, ALGORITHM
from app.use_cases.sync_service import SyncService
from app.use_cases.user_service import UserService
from app.domain.models import SyncData

router = APIRouter()
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="api/token")

class SyncRequest(BaseModel):
    device_id: str
    timestamp: float
    data: Optional[Dict[str, Any]] = {} # Сделали поле необязательным

class SyncResponse(BaseModel):
    status: str
    server_timestamp: float
    data: Dict[str, Any]

class UserCreate(BaseModel):
    username: str
    password: str

class Token(BaseModel):
    access_token: str
    token_type: str

def get_current_user(token: str = Depends(oauth2_scheme), db: Session = Depends(get_db)):
    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Could not validate credentials",
    )
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        username: str = payload.get("sub")
        user_id: int = payload.get("uid")
        if username is None or user_id is None:
            raise credentials_exception
    except JWTError:
        raise credentials_exception
    return {"username": username, "id": user_id}

@router.post("/register", response_model=Token)
def register(user: UserCreate, db: Session = Depends(get_db)):
    repo = SqlAlchemyUserRepository(db)
    service = UserService(repo)
    try:
        service.register_user(user.username, user.password)
        return service.authenticate_user(user.username, user.password)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))

@router.post("/token", response_model=Token)
def login_for_access_token(form_data: OAuth2PasswordRequestForm = Depends(), db: Session = Depends(get_db)):
    repo = SqlAlchemyUserRepository(db)
    service = UserService(repo)
    token = service.authenticate_user(form_data.username, form_data.password)
    if not token:
        raise HTTPException(status_code=401, detail="Incorrect username or password")
    return token

@router.post("/sync", response_model=SyncResponse)
def sync_data(request: SyncRequest, current_user: dict = Depends(get_current_user), db: Session = Depends(get_db)):
    repo = SqlAlchemySyncRepository(db)
    service = SyncService(repo)
    incoming_entity = SyncData(
        user_id=current_user["id"],
        data=request.data if request.data else {},
        last_updated=request.timestamp,
        device_id=request.device_id
    )
    result_entity = service.sync_user_data(incoming_entity)
    return SyncResponse(status="synced", server_timestamp=result_entity.last_updated, data=result_entity.data)