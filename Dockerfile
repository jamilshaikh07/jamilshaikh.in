FROM node:16-alpine AS builder
WORKDIR /app
COPY tailpress-master ./tailpress-master
WORKDIR /app/tailpress-master
RUN npm install
RUN npm run production
FROM docker.io/bitnami/wordpress:6.6.2-debian-12-r6
WORKDIR /bitnami/wordpress
COPY --from=builder /app/tailpress-master /bitnami/wordpress/wp-content/themes/tailpress-master
