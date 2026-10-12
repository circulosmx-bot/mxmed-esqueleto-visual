<?php
declare(strict_types=1);

namespace Identity\Http;

final class IdentityHttpCompositionSelector
{
    public const MODE_NORMAL_LOCAL = 'normal_local';
    public const MODE_PREVIEW = 'preview';
    public const MODE_PRODUCTIVE = 'productive';

    private function __construct(
        private string $environment,
        private string $mode
    ) {}

    public static function fromProcessEnvironment(): self
    {
        $app = (string)(getenv('APP_ENV') ?: '');
        $legacy = (string)(getenv('MXMED_ENVIRONMENT') ?: '');
        $normal = (string)(getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT') ?: '');
        if ($normal !== '' && $normal !== '0'
            && ($app !== 'local' || ($legacy !== '' && $legacy !== 'local'))) {
            throw new \RuntimeException('identity_composition_unavailable');
        }
        return self::fromValues($app !== '' ? $app : $legacy,
            (string)(getenv('MXMED_PREVIEW_EXPLICIT') ?: ''), $normal);
    }

    public static function fromValues(string $environment, string $explicitPreviewFlag, string $explicitNormalLocalFlag = ''): self
    {
        $environment = strtolower(trim($environment));
        if ($explicitNormalLocalFlag !== '' && $explicitNormalLocalFlag !== '0') {
            if ($explicitNormalLocalFlag !== '1' || $environment !== 'local'
                || !in_array($explicitPreviewFlag, ['', '0'], true)) {
                throw new \RuntimeException('identity_composition_unavailable');
            }
            return new self($environment, self::MODE_NORMAL_LOCAL);
        }
        if (in_array($environment, ['local', 'development'], true)) {
            if ($explicitPreviewFlag !== '1') {
                throw new \RuntimeException('identity_composition_unavailable');
            }

            return new self($environment, self::MODE_PREVIEW);
        }

        if (in_array($environment, ['staging', 'production'], true)) {
            if ($explicitPreviewFlag === '1') {
                throw new \RuntimeException('identity_composition_unavailable');
            }

            return new self($environment, self::MODE_PRODUCTIVE);
        }

        throw new \RuntimeException('identity_composition_unavailable');
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function select(callable $previewFactory, callable $productiveFactory, ?callable $normalLocalFactory = null): mixed
    {
        if ($this->mode === self::MODE_NORMAL_LOCAL) {
            if ($normalLocalFactory === null) throw new \RuntimeException('identity_composition_unavailable');
            return $normalLocalFactory();
        }
        if ($this->mode === self::MODE_PREVIEW) {
            return $previewFactory();
        }

        return $productiveFactory($this->environment);
    }
}
