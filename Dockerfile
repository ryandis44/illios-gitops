FROM python:3.12-slim

COPY wp-cli.phar /usr/local/bin/wp
RUN chmod +x /usr/local/bin/wp

WORKDIR /app
COPY main.py /app/main.py
RUN useradd -u 65532 -r -s /usr/sbin/nologin agent || true
ENTRYPOINT ["python", "/app/main.py"]
