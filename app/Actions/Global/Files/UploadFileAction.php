<?php

namespace App\Actions\Global\Files;

class UploadFileAction
{
    public function execute($file, $path, $name)
    {
        $path = $file->storeAs($path, $name, config('filesystems.documents'));

        return $path;
    }
}
