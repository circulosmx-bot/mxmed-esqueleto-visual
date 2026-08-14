<?php
declare(strict_types=1);
namespace Platform\Contracts;
interface AuditUserAgentSummarizer { public function summarizeTrustedUserAgent(string $trustedUserAgent): string; }
