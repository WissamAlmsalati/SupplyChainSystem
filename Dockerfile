FROM node:20-alpine

WORKDIR /app

# Install dependencies first (better layer caching)
COPY package*.json ./
RUN npm ci

# Copy source and build
COPY . .
RUN npm run build

# Runtime data directories
RUN mkdir -p data public/uploads

ENV NODE_ENV=production
EXPOSE 3000

CMD ["npm", "start"]
