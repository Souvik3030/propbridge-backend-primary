<?php
use App\Models\Company;

$company = Company::first();
if ($company) {
    $company->pf_client_id = 'FRyNI.Ywuqfir6hKRwMLtHaifMk7YlXUJSDIfuFX';
    $company->pf_client_secret = 'rmUJWrK9yPBmLAEpGpRN1da2R5XgbPIQ';
    $company->save();
    echo "Credentials updated correctly.\n";
} else {
    echo "No company found.\n";
}
