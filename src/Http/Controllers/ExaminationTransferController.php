<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Http\Controllers;

use HumanExaminations\HumanExaminations\Actions\ExportExamination;
use HumanExaminations\HumanExaminations\Actions\ImportExamination;
use HumanExaminations\HumanExaminations\Models\HumanExamination;
use HumanExaminations\HumanExaminations\Support\ExaminationDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExaminationTransferController
{
    public function export(Request $request, HumanExamination $examination, ExportExamination $exportExamination): StreamedResponse
    {
        Gate::authorize('view', $examination);
        $request->validate(['format' => ['sometimes', 'required', 'string', Rule::in(['json', 'csv'])]]);
        $format = $request->input('format', 'json');
        $document = $exportExamination->handle($examination);

        return response()->streamDownload(function () use ($document, $format): void {
            if ($format === 'csv') {
                $this->writeCsv($document);
            } else {
                echo json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            }
        }, 'human-examination-'.$examination->id.'.'.$format, ['Content-Type' => $format === 'csv' ? 'text/csv; charset=utf-8' : 'application/json; charset=utf-8']);
    }

    public function import(Request $request, ImportExamination $importExamination): RedirectResponse
    {
        Gate::authorize('create', HumanExamination::class);
        $request->validate(['file' => ['required', 'file', 'max:'.(int) config('human-examinations.transfer.max_file_kilobytes', 10240)]]);
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $contents = $file->get();

        if ($contents === false) {
            throw ValidationException::withMessages(['file' => 'The selected file could not be read.']);
        }

        $examination = $importExamination->handle(ExaminationDocument::decode($contents));
        $route = config('human-examinations.workspace_route');

        return is_string($route) ? redirect()->route($route, $examination) : back();
    }

    /** @param array{examination: array<string, mixed>, observations: list<array<string, mixed>>} $document */
    private function writeCsv(array $document): void
    {
        $stream = fopen('php://output', 'wb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open the export stream.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        // The export includes person_name only when the `person_name` config is set.
        $personName = array_key_exists('person_name', $document['examination']) ? ['person_name'] : [];
        $examinationFields = ['reference', 'examiner', ...$personName, 'date', 'notes'];
        fputcsv($stream, ['examination_reference', 'examiner', ...$personName, 'date', 'examination_notes', ...ExaminationDocument::OBSERVATION_FIELDS], escape: '');
        foreach ($document['observations'] ?: [[]] as $observation) {
            $values = [...array_map(fn (string $field): mixed => $document['examination'][$field] ?? null, $examinationFields), ...array_map(fn (string $field): mixed => $observation[$field] ?? null, ExaminationDocument::OBSERVATION_FIELDS)];
            fputcsv($stream, array_map(function (mixed $value): string {
                $text = (string) ($value ?? '');

                return preg_match('/^[\x00-\x20]*[=+\-@]|^[\t\r\n]/', $text) ? "'".$text : $text;
            }, $values), escape: '');
        }
        fclose($stream);
    }
}
