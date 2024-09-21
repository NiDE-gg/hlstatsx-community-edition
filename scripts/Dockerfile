FROM ubuntu:24.04

ENV DB_NAME=hlxce \
    DB_USERNAME=hlxce \
    DB_PASSWORD=hlxce \
    DB_HOST=db

COPY docker-hlxce-daemon-entrypoint /usr/local/bin/
COPY docker-hlxce-awards /usr/local/bin/

WORKDIR /home/hlxce/

COPY . /home/hlxce/

RUN apt update && apt install -y \
            curl \
            tar \
            ca-certificates \
            git \
            tar \
            musl-dev \
            bash \
            libdbi-perl \
            libperl-dev \
            libgeoip1 \
            libgeoip-dev \
            build-essential \
            libssl-dev \
            zlib1g-dev \
            libdbd-mysql-perl \
            openssl \
            unzip \
            dos2unix \
            libgeo-ip-perl\
            libsyntax-keyword-try-perl \
            libgeoip2-perl \
            libmaxmind-db-reader-perl \
        && groupadd hlxce \
        && useradd  -d /home/hlxce/ -s /bin/bash -g hlxce hlxce \
        && chmod +x /usr/local/bin/docker-hlxce-daemon-entrypoint \
        && chmod +x /usr/local/bin/docker-hlxce-awards \
        && chmod +x hlstats-awards.pl hlstats.pl hlstats-resolve.pl run_hlstats \
        && chmod +x GeoLiteCity/install_binary.sh \
        && ./GeoLiteCity/install_binary.sh \
        && chown hlxce:hlxce -R .

COPY . /home/hlxce/

RUN dos2unix /usr/local/bin/docker-hlxce-daemon-entrypoint \
    && dos2unix /usr/local/bin/docker-hlxce-awards

EXPOSE 32578/udp

ENTRYPOINT ["docker-hlxce-daemon-entrypoint"]
