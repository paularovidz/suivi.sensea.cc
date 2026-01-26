FROM node:20-alpine

WORKDIR /app

# Install dependencies
COPY package*.json yarn.lock* ./
RUN yarn install

# Copy source code
COPY . .

# Expose Astro dev server port
EXPOSE 4321

# Start Astro dev server with host binding for Docker
# usePolling pour le hot reload sur macOS/Windows avec Docker
CMD ["sh", "-c", "yarn copy-svg && yarn astro dev --host 0.0.0.0"]
