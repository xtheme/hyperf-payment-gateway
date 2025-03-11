# include environment file
include .env

docker-build:
	docker build . -t ${APP_NAME};
docker-rm:
	docker rm ${APP_NAME};
docker-start:
	docker run -d --platform=linux/amd64 -p 9501:9501 --name ${APP_NAME} ${APP_NAME}
docker-stop:
	docker stop ${APP_NAME}
