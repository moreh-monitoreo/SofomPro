<?php

namespace App\Actions;

use App\Models\Loan;
use App\Models\Score;

class IncrementScore
{
    public function handle($loan_id, $key, $search)
    {
        $score = Score::where('question', $key)->whereJsonContains('values', $search)->first();

        if($score) {
            $finalscore = number_format(($score->maximum * $score->percent) / 100, 2);
            Loan::where('id', $loan_id)->increment('score', $finalscore);

            return true;
        }

        return false;
    }
}
