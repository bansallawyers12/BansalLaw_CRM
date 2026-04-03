<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Models\Note;
use Carbon\Carbon;

class CompleteTaskRemoval extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CompleteTaskRemoval:daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove complete task after 30 day';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /*
        status =  1 =>completed task, 0=>Incompleted task
        is_action = 1 => action is done, 0 => action is not done (deleted)
        action_date => of 1 month ago
        */
        $query 	= Note::select('id')
            ->where('status', '1')
            ->where('is_action', '1')
            ->where('action_date', '<=', Carbon::now()->subDays(30)->toDateTimeString() );
        $totalNotes = $query->count();//dd($totalNotes);
		$lists = $query->get(); //dd($lists);
        if($totalNotes >0){
			foreach($lists as $key=>$val){ //dd($val->id);
                /*$appointment = Note::find($val->id);
                $appointment->is_delete = 1;
                $appointment->save();*/

                $CheckinLog = Note::find($val->id);
                $CheckinLog->delete();
            }
            $this->info('Record is deleted.');
        } else {
            $this->info('No record is found.');
        }
    }
}
