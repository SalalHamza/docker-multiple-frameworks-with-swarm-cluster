FROM php:8.2-cli-alpine

WORKDIR /app

# Install system dependencies if required for PHP extensions
RUN apk add --no-cache bash

# Copy application files
COPY . .

# Expose internal port
EXPOSE 8000

# Start PHP built-in web server listening on all network interfaces
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]