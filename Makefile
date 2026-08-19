.PHONY: install db-setup dev test install-mobile dev-mobile test-mobile

# Backend (Laravel) — primary targets
install:
	cd backend && composer install

db-setup:
	cd backend && php artisan migrate

dev:
	cd backend && php artisan serve

test:
	cd backend && php artisan test

# Mobile (Vue 3 + Capacitor) — companion targets
install-mobile:
	cd mobile && npm install

dev-mobile:
	cd mobile && npm run dev

test-mobile:
	cd mobile && npm test
