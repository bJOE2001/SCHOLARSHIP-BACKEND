<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Export report rows as CSV or a lightweight generated PDF.
     */
    public function export(Request $request): StreamedResponse|Response
    {
        abort_unless($request->user()?->isOfficer(), 403);

        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:CSV,PDF,csv,pdf'],
            'programId' => ['nullable', 'integer', 'exists:scholarship_programs,id'],
        ]);
        $type = strtoupper($validated['type'] ?? 'CSV');
        $rows = $this->reportRows($request, isset($validated['programId']) ? (int) $validated['programId'] : null);

        if ($type === 'PDF') {
            return response($this->buildPdf($this->pdfLines($rows)), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="scholarship-reports.pdf"',
            ]);
        }

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Report', 'Type', 'Generated At', 'Owner', 'Applications']);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['name'],
                    $row['type'],
                    $row['generatedAt'],
                    $row['owner'],
                    $row['applicationCount'],
                ]);
            }

            fclose($output);
        }, 'scholarship-reports.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Build report rows from live program and application records.
     *
     * @return array<int, array<string, mixed>>
     */
    private function reportRows(Request $request, ?int $programId): array
    {
        $programIds = $this->visibleProgramIds($request);
        $programs = ScholarshipProgram::query()
            ->when($programIds !== null, fn (Builder $query) => $query->whereIn('id', $programIds))
            ->when($programId !== null, fn (Builder $query) => $query->where('id', $programId))
            ->orderBy('name')
            ->get();
        $applications = ScholarshipApplication::query()
            ->when($programIds !== null, fn (Builder $query) => $query->whereIn('scholarship_program_id', $programIds))
            ->when($programId !== null, fn (Builder $query) => $query->where('scholarship_program_id', $programId))
            ->get();

        return $programs
            ->values()
            ->map(function (ScholarshipProgram $program, int $index) use ($applications): array {
                return [
                    'id' => $index + 1,
                    'programId' => $program->id,
                    'name' => $program->name.' Monitoring Report',
                    'type' => $index % 2 === 0 ? 'PDF' : 'CSV',
                    'generatedAt' => now()->format('M d, Y h:i A'),
                    'owner' => 'Scholarship Administration',
                    'applicationCount' => $applications
                        ->where('scholarship_program_id', $program->id)
                        ->count(),
                ];
            })
            ->all();
    }

    /**
     * Return scoped program ids, or null for head officer/all-access users.
     *
     * @return array<int, int>|null
     */
    private function visibleProgramIds(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || $user->isSuperAdmin()) {
            return null;
        }

        if ($user->isOfficer()) {
            return array_values(array_map('intval', $user->assigned_program_ids ?? []));
        }

        return [];
    }

    /**
     * Convert report rows into printable PDF lines.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function pdfLines(array $rows): array
    {
        $lines = [
            'Scholarship Reports',
            'Generated: '.now()->format('M d, Y h:i A'),
            '',
        ];

        foreach ($rows as $row) {
            $lines[] = $row['name'].' - '.$row['applicationCount'].' applications';
        }

        if (count($rows) === 0) {
            $lines[] = 'No report rows are available for the selected scope.';
        }

        return $lines;
    }

    /**
     * Build a small valid PDF document without adding a package dependency.
     *
     * @param  array<int, string>  $lines
     */
    private function buildPdf(array $lines): string
    {
        $content = "BT\n/F1 12 Tf\n50 742 Td\n";

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $content .= "0 -18 Td\n";
            }

            $content .= '('.$this->escapePdfText($line).") Tj\n";
        }

        $content .= "ET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $objectNumber = $index + 1;
            $pdf .= "{$objectNumber} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($index = 1; $index <= count($objects); $index++) {
            $pdf .= str_pad((string) $offsets[$index], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /**
     * Escape text for a PDF string object.
     */
    private function escapePdfText(string $line): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $line);
    }
}
