<?php
declare(strict_types=1);

namespace Identity\Http;

final class IdentityHttpCompositionSelector
{
    public const MODE_PREVIEW = 'preview';
    public const MODE_PRODUCTIVE = 'productive';

    private function __construct(
        private string $environment,
        private string $mode
    ) {}

    public static function fromProcessEnvironment(): self
    {
        return self::fromValues(
            (string)(getenv('MXMED_ENVIRONMENT') ?: ''),
            (string)(getenv('MXMED_PREVIEW_EXPLICIT') ?: '')
        );
    }

    public static function fromValues(string $environment, string $explicitPreviewFlag): self
    {
        $environment = strtolower(trim($environment));
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

    public function select(callable $previewFactory, callable $productiveFactory): mixed
    {
        if ($this->mode === self::MODE_PREVIEW) {
            return $previewFactory();
        }

        return $productiveFactory($this->environment);
    }
}
