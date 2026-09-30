<?php

namespace App\Actions\References;

use App\Enums\ReferenceType;
use App\Exceptions\References\InvalidReferenceImportException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SplFileObject;

/**
 * Creates or updates the entries of one reference table from a UTF-8 CSV file, matched on `code`.
 *
 * The first line is the header: {@see ReferenceType::requiredColumns()} plus an optional `active`
 * column (1/0, defaults to 1). Parents are given by code (`wilaya_code`, `sector_code`,
 * `profession_code`, `moughataa_code`) and must already exist. Entries missing from the file are
 * left untouched: an entry is withdrawn by importing it with `active` = 0, never deleted.
 *
 * All or nothing: every line is validated first, and nothing is written if one is invalid.
 * Re-importing the same file changes nothing.
 */
final class ImportReferences
{
    private const CODE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/';

    /**
     * @return array{created: int, updated: int, unchanged: int}
     *
     * @throws InvalidReferenceImportException
     */
    public function handle(ReferenceType $type, string $path): array
    {
        $rows = $this->validatedRows($type, $this->readRows($type, $path));
        $modelClass = $type->modelClass();
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        DB::transaction(function () use ($rows, $modelClass, &$counts): void {
            foreach ($rows as $attributes) {
                $entry = $modelClass::query()->firstOrNew(['code' => $attributes['code']]);
                $entry->fill($attributes);

                if (! $entry->exists) {
                    $counts['created']++;
                } elseif ($entry->isDirty()) {
                    $counts['updated']++;
                } else {
                    $counts['unchanged']++;
                }

                $entry->save();
            }
        });

        return $counts;
    }

    /**
     * @return array<int, array<string, string>> Rows keyed by their line number in the file.
     *
     * @throws InvalidReferenceImportException
     */
    private function readRows(ReferenceType $type, string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidReferenceImportException(["The file {$path} cannot be read."]);
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);

        $header = null;
        $rows = [];

        foreach ($file as $index => $line) {
            if (! is_array($line) || $line === [null]) {
                continue;
            }

            $line = array_map(static fn (?string $value): string => trim((string) $value), $line);

            if ($header === null) {
                $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', $line[0]) ?? $line[0];
                $header = $line;
                $missing = array_diff($type->requiredColumns(), $header);
                $unknown = array_diff($header, [...$type->requiredColumns(), 'active']);

                if ($missing !== [] || $unknown !== [] || count($header) !== count(array_unique($header))) {
                    throw new InvalidReferenceImportException([sprintf(
                        'Line 1: expected the columns %s (and optionally active), got %s.',
                        implode(', ', $type->requiredColumns()),
                        implode(', ', $header),
                    )]);
                }

                continue;
            }

            if (count($line) !== count($header)) {
                throw new InvalidReferenceImportException([sprintf(
                    'Line %d: expected %d values, got %d.',
                    $index + 1,
                    count($header),
                    count($line),
                )]);
            }

            $rows[$index + 1] = array_combine($header, $line);
        }

        if ($header === null) {
            throw new InvalidReferenceImportException(['The file is empty.']);
        }

        return $rows;
    }

    /**
     * Validates every row and resolves parent codes to their identifiers.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return list<array<string, mixed>>
     *
     * @throws InvalidReferenceImportException
     */
    private function validatedRows(ReferenceType $type, array $rows): array
    {
        $parentIds = [];

        foreach ($type->parents() as $column => $parent) {
            $parentIds[$column] = $parent['type']->modelClass()::query()->pluck('id', 'code')->all();
        }

        $errors = [];
        $seenCodes = [];
        $validated = [];

        foreach ($rows as $lineNumber => $row) {
            $validator = Validator::make($row, [
                'code' => ['required', 'string', 'regex:'.self::CODE_PATTERN],
                'name_fr' => ['required', 'string', 'max:255'],
                'name_ar' => ['required', 'string', 'max:255'],
                'type' => ['sometimes', 'required', 'string', 'regex:'.self::CODE_PATTERN],
                'active' => ['sometimes', 'nullable', 'in:0,1'],
            ]);

            foreach ($validator->errors()->all() as $message) {
                $errors[] = "Line {$lineNumber}: {$message}";
            }

            if (isset($seenCodes[$row['code']])) {
                $errors[] = "Line {$lineNumber}: the code {$row['code']} already appears on line {$seenCodes[$row['code']]}.";
            }

            $seenCodes[$row['code']] ??= $lineNumber;

            $attributes = [
                'code' => $row['code'],
                'name_fr' => $row['name_fr'],
                'name_ar' => $row['name_ar'],
                'active' => ($row['active'] ?? '') !== '0',
            ];

            if (array_key_exists('type', $row)) {
                $attributes['type'] = $row['type'];
            }

            foreach ($type->parents() as $column => $parent) {
                $parentId = $parentIds[$column][$row[$column]] ?? null;

                if ($parentId === null) {
                    $errors[] = "Line {$lineNumber}: unknown {$column} {$row[$column]}.";
                }

                $attributes[$parent['foreignKey']] = $parentId;
            }

            $validated[] = $attributes;
        }

        if ($errors !== []) {
            throw new InvalidReferenceImportException($errors);
        }

        return $validated;
    }
}
