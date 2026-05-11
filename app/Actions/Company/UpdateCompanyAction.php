<?php
declare(strict_types=1);

namespace App\Actions\Company;

use App\Models\Company;

class UpdateCompanyAction
{
    public function execute(Company $company, array $data): Company
    {
        $company->update($data);
        return $company;
    }
}