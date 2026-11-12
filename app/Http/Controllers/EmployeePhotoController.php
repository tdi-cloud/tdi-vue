<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmployeePhotoRequest;
use App\Models\Employee;
use App\Models\EmployeePhoto;
use App\Support\EmployeePhotoProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Upload, change and remove an employee's picture (superadmins only), from the
 * Employees page. Keyed by EMPCODE, never employees.id, because HR re-imports
 * the employees table every month. Answers with JSON so the card updates in place.
 */
class EmployeePhotoController extends Controller
{
    public function update(UpdateEmployeePhotoRequest $request, string $empcode, EmployeePhotoProcessor $processor): JsonResponse
    {
        $employee = $this->findEmployee($empcode);

        // Save the new file first; the old one is only removed once the record points to the new one.
        $newPath = $processor->store($request->file('photo'));

        try {
            $previousPath = DB::transaction(function () use ($employee, $newPath, $request): ?string {
                $photo = EmployeePhoto::query()
                    ->where('empcode', $employee->EMPCODE)
                    ->lockForUpdate()
                    ->first();

                $previousPath = $photo?->path;

                ($photo ?? new EmployeePhoto(['empcode' => $employee->EMPCODE]))
                    ->fill(['path' => $newPath, 'uploaded_by' => $request->user()->id])
                    ->save();

                return $previousPath;
            });
        } catch (Throwable $exception) {
            Storage::disk(EmployeePhoto::DISK)->delete($newPath);

            throw $exception;
        }

        if ($previousPath !== $newPath) {
            $processor->deleteIfUnused($previousPath);
        }

        return response()->json([
            'photo_url' => EmployeePhoto::publicUrl($newPath),
            'title' => $previousPath ? 'Profile picture changed' : 'Profile picture added',
            'message' => "{$employee->name}'s new picture now shows on the Employees page.",
        ]);
    }

    public function destroy(string $empcode, EmployeePhotoProcessor $processor): JsonResponse
    {
        $employee = $this->findEmployee($empcode);
        $photo = EmployeePhoto::query()->where('empcode', $employee->EMPCODE)->first();

        if ($photo) {
            $path = $photo->path;
            $photo->delete();
            $processor->deleteIfUnused($path);
        }

        return response()->json([
            'photo_url' => null,
            'title' => 'Profile picture removed',
            'message' => "{$employee->name} now shows the default placeholder.",
        ]);
    }

    private function findEmployee(string $empcode): Employee
    {
        return Employee::query()->where('EMPCODE', $empcode)->firstOrFail();
    }
}
