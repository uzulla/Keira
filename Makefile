build:
	docker build . -t keira

run-docker:
	docker run -it -p 8080:8080 -p 8081:8081 keira
