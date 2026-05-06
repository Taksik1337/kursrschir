from fastapi import FastAPI
from fastapi.responses import RedirectResponse # Добавили этот импорт
from app.infrastructure.database import Base, engine
from app.infrastructure.api import router
import uvicorn
Base.metadata.create_all(bind=engine)

app = FastAPI(title="Cross-Platform Sync System")

@app.get("/")
async def root():
    return RedirectResponse(url="/docs")

app.include_router(router, prefix="/api")

if __name__ == "__main__":
    # Обязательно 0.0.0.0 для работы внутри Docker
    uvicorn.run(app, host="0.0.0.0", port=8000)