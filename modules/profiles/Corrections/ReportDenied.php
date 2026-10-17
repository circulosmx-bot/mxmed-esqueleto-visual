<?php
declare(strict_types=1);
namespace Profiles\Corrections;
final class ReportDenied extends \RuntimeException
{public function __construct(public readonly int $status){parent::__construct('correction_report_denied');}}
