<?php

namespace App\Jobs;

use App\Models\Row;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use Maatwebsite\Excel\Facades\Excel;

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

        Log::info('ProcessExcelJob started', ['filePath' => $this->filePath]);

        if (!file_exists($this->filePath)) {
            $error = "Файл не найден: {$this->filePath}";
            Log::error($error);
            throw new \Exception($error);
        }

        try {
            Log::info('Начинаем чтение файла', ['filePath' => $this->filePath]);
            $rows = Excel::toArray([], $this->filePath, null, \Maatwebsite\Excel\Excel::XLSX)[0];
        } catch (NoTypeDetectedException $e) {
            $error = "Не удалось определить тип файла. Убедитесь, что файл имеет расширение .xlsx или укажите тип файла явно.";
            Log::error($error, ['exception' => $e]);
            throw new \Exception($error);
        }

        array_shift($rows);
        Log::info('Файл успешно прочитан', ['totalRows' => count($rows)]);

        foreach (array_chunk($rows, 1000) as $chunkIndex => $chunk) {
            Log::info('Обрабатываем пачку данных', ['chunkIndex' => $chunkIndex, 'chunkSize' => count($chunk)]);

            foreach ($chunk as $line => $row) {
                $lineNumber = $chunkIndex * 1000 + $line + 2;

                $validation = Validator::make($row, [
                    '0' => 'required|integer|min:1',
                    '1' => 'required|regex:/^[a-zA-Z ]+$/',
                    '2' => 'required|date_format:d.m.Y',
                ]);

                if ($validation->fails()) {
                    $errors[] = "$lineNumber - " . implode(', ', $validation->errors()->all());
                    Log::warning('Ошибка валидации', ['lineNumber' => $lineNumber, 'errors' => $validation->errors()->all()]);
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
                    Log::error('Ошибка при сохранении строки', ['lineNumber' => $lineNumber, 'exception' => $e]);
                }
            }

            Redis::incrby($progressKey, count($chunk));
            Log::info('Прогресс обновлён', ['progressKey' => $progressKey, 'processed' => Redis::get($progressKey)]);
        }

        if (!empty($errors)) {
            Storage::put('result.txt', implode("\n", $errors));
            Log::warning('Обработка завершена с ошибками', ['errorsCount' => count($errors)]);
        }

        Log::info('ProcessExcelJob завершён успешно');
    }
}
