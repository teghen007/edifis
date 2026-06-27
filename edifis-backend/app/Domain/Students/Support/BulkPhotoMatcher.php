<?php

declare(strict_types=1);

namespace App\Domain\Students\Support;

use App\Domain\Students\Models\Student;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Bulk "Photo Day" matcher: takes a .zip (or folder) of images named by a
 * student identifier and attaches each to that student's photo collection.
 *
 * A file matches by its name stem (case-insensitive) against, in order:
 *   1. master_pea_id   e.g. PEA-2026-00001.jpg
 *   2. internal id     the student UUID
 *   3. normalised name e.g. "ngwa zedtest.jpg", "zedtest_ngwa.png"
 */
class BulkPhotoMatcher
{
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];

    /** @return array{matched:int, unmatched:array<int,string>, ambiguous:array<int,string>} */
    public function fromZip(string $zipPath): array
    {
        $dir = storage_path('app/_bulk_photos_' . Str::random(8));
        @mkdir($dir, 0775, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            @rmdir($dir);

            throw new \RuntimeException('Could not open the uploaded zip file.');
        }
        $zip->extractTo($dir);
        $zip->close();

        try {
            return $this->fromDirectory($dir);
        } finally {
            $this->deleteDir($dir);
        }
    }

    /** @return array{matched:int, unmatched:array<int,string>, ambiguous:array<int,string>} */
    public function fromDirectory(string $dir): array
    {
        $result = ['matched' => 0, 'unmatched' => [], 'ambiguous' => []];

        $index = $this->buildIndex();

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, self::IMAGE_EXT, true)) {
                continue;
            }

            $stem = $this->norm(pathinfo($file->getFilename(), PATHINFO_FILENAME));
            $hits = $index[$stem] ?? [];

            if (count($hits) === 0) {
                $result['unmatched'][] = $file->getFilename();

                continue;
            }
            if (count($hits) > 1) {
                $result['ambiguous'][] = $file->getFilename();

                continue;
            }

            $student = Student::find($hits[0]);
            if (! $student) {
                $result['unmatched'][] = $file->getFilename();

                continue;
            }

            $student->clearMediaCollection('photo');
            $student->addMedia($file->getRealPath())
                ->preservingOriginal()
                ->toMediaCollection('photo');

            $result['matched']++;
        }

        return $result;
    }

    /**
     * Map every recognised identifier -> [student ids]. A list (not a single id)
     * so name collisions surface as "ambiguous" instead of silently overwriting.
     *
     * @return array<string, array<int,string>>
     */
    private function buildIndex(): array
    {
        $index = [];
        $add = function (string $key, string $id) use (&$index) {
            $key = $this->norm($key);
            if ($key === '') {
                return;
            }
            $index[$key][] = $id;
            $index[$key] = array_values(array_unique($index[$key]));
        };

        Student::query()
            ->select(['id', 'master_pea_id', 'given_name', 'family_name'])
            ->where('active', true)
            ->chunk(500, function ($students) use ($add) {
                foreach ($students as $s) {
                    if ($s->master_pea_id) {
                        $add($s->master_pea_id, $s->id);
                    }
                    $add($s->id, $s->id);
                    $add($s->given_name . ' ' . $s->family_name, $s->id);
                    $add($s->family_name . ' ' . $s->given_name, $s->id);
                }
            });

        return $index;
    }

    /** Lower-case alphanumerics only, so "PEA-2026-00001" == "pea202600001". */
    private function norm(string $v): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $v) ?? '');
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getRealPath()) : @unlink($item->getRealPath());
        }
        @rmdir($dir);
    }
}
