<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Domain\Students\Models\Student;
use App\Domain\Issuance\Models\CatalogueItem;
use App\Domain\Academics\Models\Mark;
use App\Domain\Timetable\Models\TimetableEntry;
use App\Domain\Promotion\Models\PromotionRuleset;
use App\Domain\Tenancy\Models\EdifisTenant;
use Stancl\Tenancy\Database\Models\Domain;
use Ramsey\Uuid\Uuid;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

class LabSeeder extends Seeder
{
    private ?OutputStyle $console = null;

    private const TENANTS = [
        'pssnkwen'  => ['name' => 'PSS Nkwen',  'domain' => 'nkwen.cloud.edifis.test'],
        'pssmankon' => ['name' => 'PSS Mankon', 'domain' => 'mankon.cloud.edifis.test'],
    ];

    private const USERS = [
        'bih.patience@pssnkwen.local'   => ['name' => 'Bih Patience',     'role' => 'principal'],
        'nkweta.therese@pssnkwen.local' => ['name' => 'Nkweta Therese',  'role' => 'vice_principal'],
        'nebaluices@pssnkwen.local'     => ['name' => 'Neba Luices',     'role' => 'bursar'],
        'songhi.kingsley@pssnkwen.local'=> ['name' => 'Songhi Kingsley', 'role' => 'class_master'],
        'ngufor.calvin@pssnkwen.local'  => ['name' => 'Ngufor Calvin',   'role' => 'subject_teacher'],
        'tangwo.jerome@pssnkwen.local'  => ['name' => 'Tangwo Jerome',   'role' => 'discipline_master'],
        'rita.awah@pssnkwen.local'      => ['name' => 'Rita Awah',       'role' => 'secretary'],
    ];

    private const CATALOGUE = [
        ['name' => 'Mathematics Form 1',       'cost' => 8000,  'category' => 'textbook'],
        ['name' => 'English Language Form 1',  'cost' => 6000,  'category' => 'textbook'],
        ['name' => 'Chemistry Form 1',         'cost' => 7500,  'category' => 'textbook'],
        ['name' => 'Principal of Accounts F1', 'cost' => 9000,  'category' => 'textbook'],
        ['name' => 'School Uniform (full set)','cost' => 15000, 'category' => 'uniform'],
        ['name' => 'Laboratory Fee Term 1',    'cost' => 5000,  'category' => 'lab_fee'],
        ['name' => 'Boarding Fee Term 1',      'cost' => 45000, 'category' => 'boarding'],
    ];

    public function run(): void
    {
        $this->console = new OutputStyle(
            new \Symfony\Component\Console\Input\StringInput(''),
            new ConsoleOutput()
        );

        $this->seedRoles();
        $this->seedTenants();
        $this->seedPromotionRuleset();
        $this->seedCatalogue();
        $this->seedUsers();
        $this->seedDemoMarks();
        $this->seedLabReport();

        $this->console->writeln('');
        $this->console->writeln('<info>=== DEMO LOGINS (password: secret) ===</info>');
        foreach (self::USERS as $email => $user) {
            $this->console->writeln(sprintf('  %s  %s (%s)', $email, $user['name'], $user['role']));
        }
        $this->console->writeln('');
    }

    private function seedRoles(): void
    {
        $roles = [
            'principal', 'vice_principal', 'bursar', 'class_master',
            'subject_teacher', 'discipline_master', 'secretary', 'parent',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
        }

        $this->console->writeln("[roles] " . count($roles) . " roles ensured.");
    }

    private function seedTenants(): void
    {
        foreach (self::TENANTS as $code => $info) {
            $tenant = EdifisTenant::firstOrCreate(
                ['id' => $code],
                [
                    'school_code' => $code,
                    'school_name' => $info['name'],
                    'school_location' => 'Bamenda, Cameroon',
                ]
            );

            if ($tenant->wasRecentlyCreated) {
                $tenant->domains()->create(['domain' => $info['domain']]);
                $this->console->writeln("[tenant] {$info['name']} ({$info['domain']})");
            } else {
                $this->console->writeln("[tenant] {$info['name']} (already exists — skipped)");
            }
        }
    }

    private function seedPromotionRuleset(): void
    {
        PromotionRuleset::firstOrCreate(
            ['version' => '2026-v1'],
            [
                'baseline' => 10.0,
                'coefficients' => [],
                'active' => true,
            ]
        );
        $this->console->writeln('[promotion] ruleset 2026-v1 ensured.');
    }

    private function seedCatalogue(): void
    {
        $count = 0;
        foreach (self::CATALOGUE as $item) {
            $exists = CatalogueItem::where('name', $item['name'])->exists();
            if (! $exists) {
                CatalogueItem::create([
                    'id' => (string) Uuid::uuid7(),
                    'name' => $item['name'],
                    'cost' => $item['cost'],
                    'category' => $item['category'],
                ]);
                $count++;
            }
        }
        $this->console->writeln("[catalogue] {$count} new items (rest already present).");
    }

    private function seedUsers(): void
    {
        $created = 0;
        foreach (self::USERS as $email => $info) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'id' => (string) Uuid::uuid7(),
                    'name' => $info['name'],
                    'password' => Hash::make('secret'),
                    'active' => true,
                ]
            );

            if ($user->wasRecentlyCreated) {
                $user->assignRole($info['role']);
                $created++;
            }
        }
        $this->console->writeln("[users] {$created} new users (rest already present).");
    }

    private function seedDemoMarks(): void
    {
        $existing = Mark::where('sequence', '2026-T1-Seq1')->count();
        if ($existing > 0) {
            $this->console->writeln('[marks] demo marks already exist — skipped.');
            return;
        }

        $students = [
            ['id' => (string) Uuid::uuid7(), 'name' => 'Goodness Shei',   'class' => (string) Uuid::uuid7()],
            ['id' => (string) Uuid::uuid7(), 'name' => 'John Tansi',      'class' => (string) Uuid::uuid7()],
            ['id' => (string) Uuid::uuid7(), 'name' => 'Miriam Ndefru',   'class' => (string) Uuid::uuid7()],
        ];

        foreach ($students as $stu) {
            Student::create([
                'id' => $stu['id'],
                'given_name' => explode(' ', $stu['name'])[0],
                'family_name' => explode(' ', $stu['name'])[1] ?? '',
                'current_class_id' => $stu['class'],
                'enrolled_at' => now()->subMonths(3),
            ]);
        }

        $marksData = [
            ['student' => $students[0], 'math' => 16, 'english' => 14],
            ['student' => $students[1], 'math' => 9,  'english' => 12],
            ['student' => $students[2], 'math' => 18, 'english' => 17],
        ];

        foreach ($marksData as $row) {
            Mark::create([
                'id' => (string) Uuid::uuid7(), 'revision' => 'r1',
                'student_id' => $row['student']['id'],
                'subject_id' => (string) Uuid::uuid7(),
                'class_id' => $row['student']['class'],
                'sequence' => '2026-T1-Seq1',
                'owner_teacher_id' => User::where('email', 'ngufor.calvin@pssnkwen.local')->first()?->id ?? (string) Uuid::uuid7(),
                'score' => $row['math'], 'max_score' => 20.0,
                'recorded_at' => now(),
            ]);
            Mark::create([
                'id' => (string) Uuid::uuid7(), 'revision' => 'r1',
                'student_id' => $row['student']['id'],
                'subject_id' => (string) Uuid::uuid7(),
                'class_id' => $row['student']['class'],
                'sequence' => '2026-T1-Seq1',
                'owner_teacher_id' => User::where('email', 'ngufor.calvin@pssnkwen.local')->first()?->id ?? (string) Uuid::uuid7(),
                'score' => $row['english'], 'max_score' => 20.0,
                'recorded_at' => now(),
            ]);
        }

        $this->console->writeln('[marks] demo marks seeded (3 students x 2 subjects).');
    }

    private function seedLabReport(): void
    {
        $tenantCount = EdifisTenant::count();
        $userCount = User::count();
        $catalogueCount = CatalogueItem::count();
        $markCount = Mark::count();
        $studentCount = Student::count();

        $this->console->writeln('');
        $this->console->writeln('<info>=== LAB STATE ===</info>');
        $this->console->writeln("  Tenants:  {$tenantCount}");
        $this->console->writeln("  Users:    {$userCount}");
        $this->console->writeln("  Catalogue: {$catalogueCount} items");
        $this->console->writeln("  Marks:    {$markCount}");
        $this->console->writeln("  Students: {$studentCount}");
    }
}
