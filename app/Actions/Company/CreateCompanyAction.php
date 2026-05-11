<?php
declare(strict_types=1);

namespace App\Actions\Company;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateCompanyAction
{
    public function execute(array $data): Company
    {
        $data['is_active'] = $data['is_active'] ?? true;

        try {
            return DB::transaction(function () use ($data) {
                return Company::create($data);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                throw new ConflictHttpException('A company with this name or domain is already being created. Please try a different one.');
            }
            throw $e; 
        }
    }
}