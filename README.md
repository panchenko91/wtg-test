### 1. Клонувати репозиторій

```bash
git clone git@github.com:panchenko91/wtg-test.git wtg-test
cd wtg-test
```

### 2. .env
```bash
cp .env.example .env
```
У `.env` заповнити пароль до бази — в `.env.example` він порожній:
```
DB_PASSWORD=12345678
```

### 3. docker-compose.yml
```bash
cp docker-compose.example.yml docker-compose.yml
```

### 4. Підняти контейнери
```bash
docker compose up -d
```

### 5. Встановити залежності та згенерувати ключ
```bash
docker compose exec php composer install
docker compose exec php php artisan key:generate
```

### 6. Міграції та seeders
```bash
docker compose exec php php artisan migrate --seed
```

### 7. Перезапустити queue worker
```bash
docker compose up -d queue
```

## Тести
```bash
docker compose exec php php artisan test
```

## Захист від подвійного бронювання
Песимістичне блокування рядка через `SELECT ... FOR UPDATE`.
