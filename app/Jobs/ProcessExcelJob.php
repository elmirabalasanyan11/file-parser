<?php

namespace App\Jobs;

use App\Models\Row;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProcessExcelJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    public function handle()
    {
        $errors = [];
        $progressKey = 'excel_processing_' . uniqid();
        Redis::set($progressKey, 0);

        if (!file_exists($this->filePath)) {
            throw new \Exception("Файл не найден: $this->filePath");
        }

        try {
            $rows = Excel::toArray([], $this->filePath, null, \Maatwebsite\Excel\Excel::XLSX)[0];
        } catch (NoTypeDetectedException $e) {
            throw new \Exception("Не удалось определить тип файла. Убедитесь, что файл имеет расширение .xlsx или укажите тип файла явно.");
        }

        array_shift($rows);

        foreach (array_chunk($rows, 1000) as $chunkIndex => $chunk) {
            foreach ($chunk as $row) {
                $lineNumber = "$row[0] $row[1]";

                $validation = Validator::make([
                    'id' => $row[0] ?? null,
                    'name' => $row[1] ?? null,
                    'date' => $row[2] ?? null,
                ], [
                    'id' => 'required|integer|min:1',
                    'name' => 'required|regex:/^[a-zA-Z ]+$/',
                    'date' => 'required|date_format:d.m.Y',
                ]);

                if ($validation->fails()) {
                    $errors[] = "$lineNumber - " . implode(', ', $validation->errors()->all());
                    continue;
                }

                if (Row::where('external_id', $row[0])->exists()) {
                    $errors[] = "$lineNumber - Дубликат id: {$row[0]}";
                    continue;
                }

                try {
                    Row::create([
                        'external_id' => $row[0],
                        'name' => $row[1],
                        'date' => \Carbon\Carbon::createFromFormat('d.m.Y', $row[2])->toDateString(),
                    ]);
                } catch (\Exception $e) {
                    $errors[] = "$lineNumber - Ошибка при сохранении: " . $e->getMessage();
                }
            }

            Redis::incrby($progressKey, count($chunk));
        }

        Storage::put('result.txt', implode("\n", $errors));
    }
}
