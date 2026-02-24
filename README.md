## Описание проекта
- app/Http/Controllers/TrainPathController - контроллер обработчик запросов
- app/DTO/TrainPathRequestDTO - DTO для данных с фронтенда
- app/Services/SoapRequestService - Сервис отправки запросов на SOAP
- app/Services/RedisCacheService -Сервис для кеширования данных в Redis
- app/Services/PathFindService - Сервис для выборки станций остановок внутри маршрута
- Вместо Soap запроса числового кода станции по ее названию был реализован метод-заглушка

## Стек
- PHP (Laravel, PHPUnit) 
- Redis
- Docker

## Инструкция по запуску проекта
1. Скопировать .env.example в .env файл и дополнить конфигами для авторизации к soap сервису.
```text
SOAP_WSDL=
AUTH_LOGIN=
AUTH_PASSWORD=
AUTH_TERMINAL=
AUTH_REPRESENT_ID=
LANGUAGE=
CURRENCY=
```

2. Поднять контейнеры командой 'make up' или:
```bash
docker compose up -d --build
```

3. Установить необходимые зависимости командой 'make setup' или:
```bash
docker exec -it php-train composer setup
```

4. Открыть страницу - http://localhost:8080/

5. Для запуска тестов выполнить команду 'make test' или:
```bash
docker exec -it php-train php artisan test
```
