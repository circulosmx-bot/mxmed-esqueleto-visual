ARG PHP_BASE_IMAGE
FROM ${PHP_BASE_IMAGE}

ARG PHP_BASE_IMAGE
ARG PHPREDIS_VERSION=6.2.0
ARG AWS_CLI_VERSION=2.28.21
ARG AWS_CLI_ZIP_SHA256
ARG SOURCE_REVISION

RUN case "${PHP_BASE_IMAGE}" in *@sha256:[0-9a-f][0-9a-f]*) ;; *) exit 41 ;; esac \
    && test -n "${AWS_CLI_ZIP_SHA256}" \
    && printf '%s' "${SOURCE_REVISION}" | grep -Eq '^[0-9a-f]{40}$' \
    && apt-get update \
    && apt-get install --yes --no-install-recommends ca-certificates coreutils curl jq unzip \
    && pecl install "redis-${PHPREDIS_VERSION}" \
    && docker-php-ext-enable redis \
    && curl --fail --silent --show-error --location \
      "https://awscli.amazonaws.com/awscli-exe-linux-x86_64-${AWS_CLI_VERSION}.zip" \
      --output /tmp/awscliv2.zip \
    && echo "${AWS_CLI_ZIP_SHA256}  /tmp/awscliv2.zip" | sha256sum --check --strict \
    && unzip -q /tmp/awscliv2.zip -d /tmp \
    && /tmp/aws/install --bin-dir /usr/local/bin --install-dir /usr/local/aws-cli \
    && rm -rf /tmp/aws /tmp/awscliv2.zip /var/lib/apt/lists/* \
    && useradd --create-home --uid 10001 --shell /usr/sbin/nologin mxmed

WORKDIR /app
LABEL org.opencontainers.image.revision="${SOURCE_REVISION}"
COPY --chown=10001:10001 modules/identity/adapters /app/modules/identity/adapters
COPY --chown=10001:10001 modules/identity/contracts /app/modules/identity/contracts
COPY --chown=10001:10001 modules/identity/services /app/modules/identity/services
COPY --chown=10001:10001 modules/identity/tests/C3ValkeySessionStoreIntegrationTest.php /app/modules/identity/tests/C3ValkeySessionStoreIntegrationTest.php
COPY --chown=10001:10001 infra/aws/c3-runner/entrypoint.sh /opt/mxmed/entrypoint.sh
RUN chmod 0555 /opt/mxmed/entrypoint.sh \
    && find /app -type d -exec chmod 0555 {} + \
    && find /app -type f -exec chmod 0444 {} +

USER 10001:10001
ENTRYPOINT ["/opt/mxmed/entrypoint.sh"]
