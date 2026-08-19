.PHONY: install db-setup dev test install-mobile dev-mobile test-mobile dev-all

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

# Run backend (web) and mobile dev servers together; Ctrl+C stops both.
dev-all:
	@trap 'kill 0' EXIT INT TERM; \
	(cd backend && php artisan serve) & \
	(cd mobile && npm run dev) & \
	wait
