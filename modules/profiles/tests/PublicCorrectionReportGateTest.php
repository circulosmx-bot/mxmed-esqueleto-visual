<?php
declare(strict_types=1);

require_once __DIR__ . '/../controllers/PublicProfileController.php';

use Profiles\Controllers\PublicProfileController;
use Profiles\Repositories\PublicProfileRepository;

/** Real repository/controller over in-memory SQLite; only metadata syntax is adapted. */
final class CorrectionGateMemoryPdo extends PDO
{
    public bool $failMembershipRead = false;
    public int $reads = 0;

    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!preg_match('/^\s*SELECT\b/i', $query)) {
            throw new RuntimeException('test repository attempted a write');
        }
        $this->reads++;
        if ($this->failMembershipRead && preg_match('/FROM\s+`?auth_account_memberships`?/i', $query)) {
            throw new PDOException('synthetic unavailable membership source');
        }
        if (str_contains($query, 'information_schema.tables')) {
            $query = "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:table";
        } elseif (str_contains($query, 'information_schema.columns')) {
            $query = 'SELECT name FROM pragma_table_info(:table)';
        }
        return parent::prepare($query, $options);
    }
}

$checks = 0;
function correctionGateCheck(bool $pass, string $label): void
{
    global $checks;
    if (!$pass) throw new RuntimeException($label);
    $checks++;
}

function correctionGateFixture(?string $membershipSchema, ?array $membership = null, string $plan = 'free'): CorrectionGateMemoryPdo
{
    $pdo = new CorrectionGateMemoryPdo();
    $pdo->exec('CREATE TABLE profiles_doctors (doctor_id TEXT PRIMARY KEY,display_name TEXT,professional_license TEXT,specialty_primary TEXT,plan_code TEXT,profile_status TEXT,is_public_candidate INTEGER)');
    $pdo->exec("INSERT INTO profiles_doctors VALUES ('synthetic-correction','Profesional sintético','SYNTHETIC-NOT-A-LICENSE','Especialidad sintética','" . $plan . "','active',1)");
    $pdo->exec('CREATE TABLE consultorios (doctor_id TEXT,consultorio_id TEXT,titulo TEXT)');
    $pdo->exec("INSERT INTO consultorios VALUES ('synthetic-correction','office-synthetic','Consultorio sintético')");
    if ($membershipSchema !== null) $pdo->exec('CREATE TABLE auth_account_memberships (' . $membershipSchema . ')');
    if ($membership !== null) {
        $quote = static fn(string $value): string => $pdo->quote($value);
        $pdo->exec('INSERT INTO auth_account_memberships VALUES (' . implode(',', array_map($quote, $membership)) . ')');
    }
    return $pdo;
}

function correctionGateVisible(CorrectionGateMemoryPdo $pdo): bool
{
    $before = $pdo->query('SELECT total_changes()')->fetchColumn();
    $response = (new PublicProfileController(new PublicProfileRepository($pdo)))->showByDoctorId('synthetic-correction');
    correctionGateCheck(($response['ok'] ?? false) === true, 'canonical public DTO is returned');
    correctionGateCheck($before === $pdo->query('SELECT total_changes()')->fetchColumn(), 'public projection performs no data changes');
    correctionGateCheck($pdo->reads > 0, 'native public repository reads executed');
    return (bool)($response['data']['public_visibility']['show_suggest_correction'] ?? false);
}

$schema = 'profile_doctor_id TEXT,role_code TEXT,scope_code TEXT,status TEXT';
correctionGateCheck(!correctionGateVisible(correctionGateFixture(null)), 'missing membership table fails closed in actual controller');
correctionGateCheck(!correctionGateVisible(correctionGateFixture('profile_doctor_id TEXT')), 'incomplete membership schema fails closed');
$unavailable = correctionGateFixture($schema);
$unavailable->failMembershipRead = true;
correctionGateCheck(!correctionGateVisible($unavailable), 'membership query failure fails closed');
correctionGateCheck(correctionGateVisible(correctionGateFixture($schema)), 'free profile with known empty membership source remains eligible');
foreach (['owner', 'administrator', 'collaborator'] as $role) {
    correctionGateCheck(!correctionGateVisible(correctionGateFixture($schema, ['synthetic-correction', $role, 'profile', 'active'])), 'active administered profile hidden: ' . $role);
}
correctionGateCheck(correctionGateVisible(correctionGateFixture($schema, ['synthetic-correction', 'owner', 'profile', 'revoked'])), 'revoked membership is not active administration');
correctionGateCheck(correctionGateVisible(correctionGateFixture($schema, ['other-synthetic-profile', 'owner', 'profile', 'active'])), 'another profile membership does not administer this profile');
foreach (['basic', 'standard', 'optimum', 'professional'] as $plan) {
    correctionGateCheck(!correctionGateVisible(correctionGateFixture($schema, null, $plan)), 'paid gate preserved: ' . $plan);
}

echo 'PublicCorrectionReportGateTest PASS ' . $checks . " assertions; in-memory only\n";
