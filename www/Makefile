.PHONY: dev build preview install clean test format check

# Install dependencies
install:
	npm install

# Development server with hot reload (watch mode)
dev:
	npm run dev

# Build for production
build:
	npm run build

# Build and preview production build
preview:
	npm run preview

# Run tests in watch mode
test:
	npm test

# Format code with Prettier
format:
	npm run format

# TypeScript check
check:
	npm run astro-check

# Generate image variants
images:
	npm run generate-images

# Generate image variants (force regeneration)
images-force:
	npm run generate-images-force

# Generate favicons
favicons:
	npm run generate-favicons

# Clean build artifacts
clean:
	rm -rf dist node_modules/.cache
