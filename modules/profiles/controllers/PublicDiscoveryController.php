<?php
declare(strict_types=1);

namespace Profiles\Controllers;

use Profiles\Repositories\PublicDiscoveryRepository;
use Profiles\Services\PublicProfileEligibility;
use Profiles\Services\PublicProfilePlanCapabilities;

require_once __DIR__ . '/../repositories/PublicDiscoveryRepository.php';
require_once __DIR__ . '/../services/PublicProfileEligibility.php';
require_once __DIR__ . '/../services/PublicProfilePlanCapabilities.php';

final class PublicDiscoveryController
{
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 50;

    public function __construct(private PublicDiscoveryRepository $repository) {}

    public function index(array $query): array
    {
        $validated = $this->validate($query);
        if ($validated['errors'] !== []) {
            return $this->response(false, 'invalid_params', 'invalid query parameters', null, [
                'fields' => $validated['errors'],
            ]);
        }

        $page = $validated['page'];
        $pageSize = $validated['page_size'];
        $result = $this->repository->search($validated['filters'], $page, $pageSize);
        $items = [];
        foreach ($result['items'] as $row) {
            $location = is_array($row['location'] ?? null) ? $row['location'] : [];
            $eligible = PublicProfileEligibility::hasMinimumPublicData(
                ['display_name' => $row['display_name'] ?? null],
                [
                    'professional_license' => $row['professional_license'] ?? null,
                    'specialty_primary' => $row['specialty_primary'] ?? null,
                ],
                [],
                [[
                    'consultorio_id' => $location['consultorio_id'] ?? null,
                    'is_public' => true,
                    'is_active' => true,
                ]]
            );
            if (!$eligible) {
                continue;
            }

            $capabilities = PublicProfilePlanCapabilities::build($row['plan_code'] ?? null, [
                'plan_source' => ($row['plan_code'] ?? null) === null ? 'default_free' : 'profiles_doctors',
                'has_public_profile' => true,
                'public_contact_source_ready' => false,
                'claim_source_ready' => false,
                'commercial_source_ready' => false,
            ]);
            $visibility = (array)$capabilities['public_visibility'];
            $items[] = [
                'doctor_id' => (string)$row['doctor_id'],
                'display_name' => $this->text($row['display_name'] ?? null),
                'prefix' => $this->text($row['prefix'] ?? null),
                'primary_specialty' => $this->text($row['specialty_primary'] ?? null),
                'photo_url' => (bool)($visibility['show_photo'] ?? false)
                    ? $this->text($row['photo_url'] ?? ($row['avatar_url'] ?? null))
                    : null,
                'logo_url' => (bool)($visibility['show_logo'] ?? false)
                    ? $this->text($row['logo_url'] ?? null)
                    : null,
                'location' => [
                    'consultorio_id' => (string)($location['consultorio_id'] ?? ''),
                    'name' => $this->text($location['titulo'] ?? null),
                    'address_summary' => $this->addressSummary($location),
                    'city' => $this->text($location['municipio'] ?? null),
                    'state' => $this->text($location['estado'] ?? null),
                ],
                'has_public_agenda' => (bool)($capabilities['agenda_public']['enabled'] ?? false),
                'profile_url' => '/profiles/doctor.php?doctor_id=' . rawurlencode((string)$row['doctor_id']),
            ];
        }

        $total = (int)$result['total'];
        $totalPages = $total === 0 ? 0 : (int)ceil($total / $pageSize);
        return $this->response(true, null, '', [
            'entity_type' => 'MEDICO',
            'items' => $items,
        ], [
            'filters' => $validated['filters'],
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'result_count' => count($items),
                'total_count' => $total,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
            'ordering' => ['normalized_display_name', 'doctor_id'],
            'primary_specialty_only' => true,
        ]);
    }

    private function validate(array $query): array
    {
        $errors = [];
        $filters = [];
        foreach (['state' => 120, 'city' => 120, 'specialty' => 190] as $field => $maximum) {
            $value = trim((string)($query[$field] ?? ''));
            if (strlen($value) > $maximum || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                $errors[$field] = 'invalid';
            }
            $filters[$field] = $value;
        }

        $pageRaw = trim((string)($query['page'] ?? '1'));
        $pageSizeRaw = trim((string)($query['page_size'] ?? (string)self::DEFAULT_PAGE_SIZE));
        if (preg_match('/\A[1-9][0-9]*\z/D', $pageRaw) !== 1) {
            $errors['page'] = 'positive_integer_required';
        }
        if (preg_match('/\A[1-9][0-9]*\z/D', $pageSizeRaw) !== 1) {
            $errors['page_size'] = 'positive_integer_required';
        }
        $page = isset($errors['page']) ? 1 : (int)$pageRaw;
        $pageSize = isset($errors['page_size'])
            ? self::DEFAULT_PAGE_SIZE
            : min((int)$pageSizeRaw, self::MAX_PAGE_SIZE);

        return [
            'errors' => $errors,
            'filters' => $filters,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    private function addressSummary(array $location): ?string
    {
        $street = array_values(array_filter([
            $this->text($location['calle'] ?? null),
            $this->text($location['num_ext'] ?? null),
            $this->text($location['num_int'] ?? null),
        ]));
        $parts = [];
        if ($street !== []) {
            $parts[] = implode(' ', $street);
        }
        foreach (['colonia', 'cp', 'municipio', 'estado'] as $field) {
            $value = $this->text($location[$field] ?? null);
            if ($value !== null) {
                $parts[] = $value;
            }
        }
        return $parts === [] ? null : implode(', ', $parts);
    }

    private function text($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function response(bool $ok, ?string $error, string $message, ?array $data, array $meta): array
    {
        return [
            'ok' => $ok,
            'error' => $error,
            'message' => $message,
            'data' => $data,
            'meta' => $meta + [
                'contract' => 'public_medico_discovery',
                'version' => 'PDB-02',
                'generated_at' => gmdate('c'),
            ],
        ];
    }
}
