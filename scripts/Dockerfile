FROM ubuntu:24.04

# DB_PASSWORD has no default: the entrypoints refuse to start without it
ENV DB_NAME=hlxce \
    DB_USERNAME=hlxce \
    DB_HOST=db

WORKDIR /home/hlxce/

# Runtime packages only: every Perl module comes from a package, nothing is compiled.
# curl, ca-certificates and tar are for GeoLiteCity/install_binary.sh.
RUN apt-get update && apt-get install -y --no-install-recommends \
            ca-certificates \
            curl \
            dos2unix \
            tar \
            libdbi-perl \
            libdbd-mysql-perl \
            libsyntax-keyword-try-perl \
            libgeoip2-perl \
            libmaxmind-db-reader-perl \
            libmaxmind-db-reader-xs-perl \
        && rm -rf /var/lib/apt/lists/* \
        && groupadd -g 10001 hlxce \
        && useradd -u 10001 -d /home/hlxce/ -s /bin/bash -g hlxce hlxce \
        && chown 10001:10001 /home/hlxce

# Owned by the runtime user: the entrypoints rewrite hlstats.conf in this folder
COPY --chown=10001:10001 . /home/hlxce/

# Optional "maxmind_license_key" build secret: install_binary.sh then downloads
# the current GeoLite2-City database instead of using the bundled one
# (docker build --secret id=maxmind_license_key,env=MAXMIND_LICENSE_KEY ...)
RUN --mount=type=secret,id=maxmind_license_key \
    mv docker-hlxce-daemon-entrypoint docker-hlxce-awards /usr/local/bin/ \
        && dos2unix /usr/local/bin/docker-hlxce-daemon-entrypoint /usr/local/bin/docker-hlxce-awards \
        && chmod +x /usr/local/bin/docker-hlxce-daemon-entrypoint \
        && chmod +x /usr/local/bin/docker-hlxce-awards \
        && chmod +x hlstats-awards.pl hlstats.pl hlstats-resolve.pl run_hlstats \
        && chmod +x GeoLiteCity/install_binary.sh \
        && ./GeoLiteCity/install_binary.sh \
        && chown -R 10001:10001 GeoLiteCity

EXPOSE 32578/udp

# Set by the CI build; the daemon reports them to the web frontend (Admin > Daemon Control)
ARG BUILD_COMMIT=""
ARG BUILD_DATE=""
ENV HLX_BUILD_COMMIT=${BUILD_COMMIT} \
    HLX_BUILD_DATE=${BUILD_DATE}

# Numeric, so Kubernetes can check runAsNonRoot
USER 10001:10001

ENTRYPOINT ["docker-hlxce-daemon-entrypoint"]
