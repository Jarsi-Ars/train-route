up:
	docker compose up -d --build

down:
	docker compose down

setup:
	docker exec -it php-train composer setup

test:
	docker exec -it php-train php artisan test

php:
	docker exec -it php-train sh
