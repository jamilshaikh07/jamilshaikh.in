FROM node:16-alpine
WORKDIR /app
COPY portfolio_1-main ./portfolio_1-main
WORKDIR /app/portfolio_1-main
RUN npm install
RUN npm run build
EXPOSE 3000
CMD ["npm", "run", "start"]