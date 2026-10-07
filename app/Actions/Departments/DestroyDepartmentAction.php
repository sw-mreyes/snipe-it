<?php

namespace App\Actions\Departments;

use App\Enums\FileStorage;
use App\Exceptions\ItemStillHasUsers;
use App\Models\Department;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DestroyDepartmentAction
{
    /**
     * @throws ItemStillHasUsers
     */
    public static function run(Department $department): bool
    {
        $department->loadCount(['users as users_count']);

        if ($department->users_count > 0) {
            throw new ItemStillHasUsers($department);
        }

        if ($department->image) {
            try {
                Storage::disk('public')->delete(FileStorage::Departments->publicPath().$department->image);
            } catch (\Exception $e) {
                Log::info($e->getMessage());
            }
        }

        $department->delete();

        return true;
    }
}
