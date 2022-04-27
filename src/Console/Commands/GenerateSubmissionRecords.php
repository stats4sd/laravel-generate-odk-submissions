<?php

namespace Stats4sd\GenerateOdkSubmissions\Console\Commands;

use Stats4sd\GenerateOdkSubmissions\Imports\XlsformImport;
use Carbon\Carbon;
use Faker\Generator;
use Faker\Provider\DateTime;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use ParseError;
use Stats4sd\GenerateOdkSubmissions\Models\Submission;
use Stats4sd\GenerateOdkSubmissions\Services\SubmissionGenerator;
use Stats4sd\KoboLink\Models\Team;
use Stats4sd\KoboLink\Models\TeamXlsform;

/**
 * Command to generate a fake submission in the submissions table for the selected ODK form.
 * - It reads the XLS file and generates a submission in the correct format, parsing the calculations / XPath expressions where possible.
 * - It can handle any number of nested repeat groups, and will create correctly formatted nested JSON `content`.
 *
 * TODO: add capacity for reading constraint columns.
 * TODO: add capacity for reading relevant columns.
 *
 * TODO: add capacity for user to specify output type or ranges for text, integer, decimal, calculate fields within the XLS file itself.
 * TODO: extract into a package (seperate to Kobo Link? Or part of it?)
 */
class GenerateSubmissionRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kobo:generate-subs {team_xlsform?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uses the xlsform definition to generate fake submission records for a given team_xlsform';

    /**
     * @var Generator
     */
    protected $faker; // Uses faker to generate random values (similar to Model Factories);
    protected TeamXlsform $teamXlsform; // The teamxlsform to generate a submission for.
    protected ?Collection $variables; // Collection containing all the variables pulled from the xlsform definition (survey sheet)
    protected ?Collection $choices; // Collection containing all the choices pulled from the xlsform definition (choices sheet)
    protected Collection $content; // Collection containing the content of the full submission

    /**
     * Create a new command instance.
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function __construct($variables = null, $choices = null)
    {
        parent::__construct();
        $this->variables = $variables;
        $this->choices = $choices;
        $this->faker = $this->withFaker();
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws BindingResolutionException
     */
    public function handle()
    {
        if ($this->argument('team_xlsform')) {
            // get the correct xlsform
            $this->teamXlsform = TeamXlsform::find($this->argument('team_xlsform'));
        } else {
            // ask user which team / form to use:
            $team = $this->choice('Which team do you want to use?', Team::all()->pluck('name', 'id')->toArray());

            $team_xlsform = $this->choice('Which xlsform do you want to use?', Team::where('name', $team)->first()->teamXlsforms->pluck('title', 'id')->toArray());
            $this->teamXlsform = TeamXlsform::all()->where('title', $team_xlsform)->first();
        }

        $this->info('you chose ... ' . $this->teamXlsform->title);
        if (!$this->confirm('Do you wish to continue to generate submissions for this form?', true)) {
            $this->info('exiting!');
            return Command::INVALID;
        }

        // get survey + choices sheet data;
        // returns nested collection:
        //        [
        //            'survey' => Collection of rows;
        //            'choices' => Collection of rows;
        //        ]
        $collection = Excel::toCollection(new XlsformImport, Storage::disk(config('kobo-link.xlsforms.storage_disk'))->path($this->teamXlsform->xlsform->xlsfile));

        $variables = collect([]);

        // get list of types and names from survey:
        foreach ($collection['survey'] as $index => $variable) {
            $variables->push([
                'index' => $index,
                'type' => $variable['type'],
                'name' => $variable['name'],
                'appearance' => $variable['appearance'] ?? null, // to check if select is from an external csv (i.e. a database table)
                'repeat_count' => $variable['repeat_count'] ?? null, // for generating the appropriate number of repeats;
                'calculation' => $variable['calculation'] ?? null,
                // Note yet used
                // 'relevant' => $variable['relevant'],
                // 'constraint' => $variable['constraint'],
            ]);

        }

        // get choice lists
        $this->choices = $collection['choices']->groupBy('list_name');
        unset($this->choices[""]);

        // $choiceNames = $this->choices->map(fn($choice) => $choice->pluck('name'));

        // process variables;
        $generator = (new SubmissionGenerator($this->teamXlsform, $variables, $this->choices, collect([])));

        $entry = $generator->processVariablesSequentially();

        // add 'kobo' metadata
        $submissionIdMax = Submission::orderBy('id', 'desc')->take(1)->get()->first()->id;

        $entry['formhub/uuid'] = $this->faker->uuid();
        $entry['_id'] = $submissionIdMax + 1;
        $entry['meta/instanceID'] = 'uuid:' . $entry['formhub/uuid'];
        $entry['_status'] = 'fake submission';
        $entry['_submission_time'] = Carbon::now()->format('Y-m-d') . 'T' . Carbon::now()->format('H-m-s');
        $entry['_tags'] = [];
        $entry['_notes'] = [];
        $entry['_attachments'] = [];
        $entry['_validation_status'] = [];
        $entry['submitted_by'] = "Custom generation code";

        // dump($submission);
        $this->teamXlsform->submissions()->create([
            'id' => $entry['_id'],
            'uuid' => $entry['formhub/uuid'],
            'submitted_at' => $entry['_submission_time'],
            'content' => $entry->toJson(),
        ]);


        return Command::SUCCESS;
    }


    /**
     * Get a new Faker instance.
     *
     * @return Generator
     * @throws BindingResolutionException
     */
    protected function withFaker(): Generator
    {
        return Container::getInstance()->make(Generator::class);
    }

}
