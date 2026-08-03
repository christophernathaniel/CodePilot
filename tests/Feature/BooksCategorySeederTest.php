<?php

use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use App\Support\Snippets\GuideStepParser;
use Database\Seeders\BooksCategorySeeder;
use Illuminate\Support\Str;

test('it seeds detailed reading notes into the Books category for the development account', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);
    $otherUser = User::factory()->create(['email' => 'someone@example.com']);

    $this->seed(BooksCategorySeeder::class);

    $category = $user->libraryCategories()
        ->where('name', 'Books')
        ->sole();
    $project = Project::query()
        ->whereBelongsTo($user)
        ->whereBelongsTo($category, 'libraryCategory')
        ->where('name', 'Reading Notes')
        ->with(['snippets.tags', 'snippets.variations'])
        ->sole();
    $books = $project->snippets->sortBy('position')->values();

    expect($project->kind)->toBe(Project::KIND_GUIDE)
        ->and($project->description)->toContain('83 books', 'essential learnings')
        ->and($books)->toHaveCount(83)
        ->and($books->pluck('filename')->all())->toBe([
            '01-solito-javier-zamora.guide.md',
            '02-just-another-mountain-sarah-jane-douglas.guide.md',
            '03-breathtaking-rachel-clarke.guide.md',
            '04-the-fear-bubble-ant-middleton.guide.md',
            '05-with-the-end-in-mind-kathryn-mannix.guide.md',
            '06-breath-james-nestor.guide.md',
            '07-the-comfort-crisis-michael-easter.guide.md',
            '08-the-in-between-hadley-vlahos.guide.md',
            '09-lands-of-lost-borders-kate-harris.guide.md',
            '10-wild-cheryl-strayed.guide.md',
            '11-surrounded-by-idiots-thomas-erikson.guide.md',
            '12-still-alice-lisa-genova.guide.md',
            '13-quiet-susan-cain.guide.md',
            '14-the-microstress-effect-rob-cross-karen-dillon.guide.md',
            '15-exercised-daniel-e-lieberman.guide.md',
            '16-the-body-keeps-the-score-bessel-van-der-kolk.guide.md',
            '17-im-so-effing-tired-amy-shah.guide.md',
            '18-move-the-body-heal-the-mind-jennifer-heisz.guide.md',
            '19-when-it-is-darkest-rory-oconnor.guide.md',
            '20-forgetting-scott-a-small.guide.md',
            '21-how-we-learn-benedict-carey.guide.md',
            '22-lost-connections-johann-hari.guide.md',
            '23-life-worth-living-miroslav-volf.guide.md',
            '24-you-are-not-alone-cariad-lloyd.guide.md',
            '25-noise-daniel-kahneman-olivier-sibony-cass-sunstein.guide.md',
            '26-why-we-sleep-matthew-walker.guide.md',
            '27-essential-strategies-for-social-anxiety-alison-mckleroy.guide.md',
            '28-why-we-cant-sleep-ada-calhoun.guide.md',
            '29-abundance-ezra-klein-derek-thompson.guide.md',
            '30-careless-people-sarah-wynn-williams.guide.md',
            '31-the-let-them-theory-mel-robbins.guide.md',
            '32-the-intermittent-fasting-revolution-mark-p-mattson.guide.md',
            '33-when-breath-becomes-air-paul-kalanithi.guide.md',
            '34-mans-search-for-meaning-viktor-e-frankl.guide.md',
            '35-the-choice-edith-eger.guide.md',
            '36-night-elie-wiesel.guide.md',
            '37-the-happiest-man-on-earth-eddie-jaku.guide.md',
            '38-the-hiding-place-corrie-ten-boom.guide.md',
            '39-educated-tara-westover.guide.md',
            '40-the-glass-castle-jeannette-walls.guide.md',
            '41-know-my-name-chanel-miller.guide.md',
            '42-i-am-malala-malala-yousafzai-christina-lamb.guide.md',
            '43-long-walk-to-freedom-nelson-mandela.guide.md',
            '44-the-autobiography-of-malcolm-x-malcolm-x-alex-haley.guide.md',
            '45-born-a-crime-trevor-noah.guide.md',
            '46-the-sun-does-shine-anthony-ray-hinton.guide.md',
            '47-just-mercy-bryan-stevenson.guide.md',
            '48-between-the-world-and-me-ta-nehisi-coates.guide.md',
            '49-the-warmth-of-other-suns-isabel-wilkerson.guide.md',
            '50-when-they-call-you-a-terrorist-patrisse-khan-cullors-asha-bandele.guide.md',
            '51-being-mortal-atul-gawande.guide.md',
            '52-the-last-lecture-randy-pausch-jeffrey-zaslow.guide.md',
            '53-tuesdays-with-morrie-mitch-albom.guide.md',
            '54-the-year-of-magical-thinking-joan-didion.guide.md',
            '55-a-grief-observed-c-s-lewis.guide.md',
            '56-crying-in-h-mart-michelle-zauner.guide.md',
            '57-the-diving-bell-and-the-butterfly-jean-dominique-bauby.guide.md',
            '58-brain-on-fire-susannah-cahalan.guide.md',
            '59-an-unquiet-mind-kay-redfield-jamison.guide.md',
            '60-the-center-cannot-hold-elyn-r-saks.guide.md',
            '61-maybe-you-should-talk-to-someone-lori-gottlieb.guide.md',
            '62-do-no-harm-henry-marsh.guide.md',
            '63-this-is-going-to-hurt-adam-kay.guide.md',
            '64-war-doctor-david-nott.guide.md',
            '65-the-language-of-kindness-christie-watson.guide.md',
            '66-the-man-who-mistook-his-wife-for-a-hat-oliver-sacks.guide.md',
            '67-mountains-beyond-mountains-tracy-kidder.guide.md',
            '68-evicted-matthew-desmond.guide.md',
            '69-nickel-and-dimed-barbara-ehrenreich.guide.md',
            '70-maid-stephanie-land.guide.md',
            '71-nomadland-jessica-bruder.guide.md',
            '72-the-immortal-life-of-henrietta-lacks-rebecca-skloot.guide.md',
            '73-hidden-valley-road-robert-kolker.guide.md',
            '74-empire-of-pain-patrick-radden-keefe.guide.md',
            '75-bad-blood-john-carreyrou.guide.md',
            '76-the-radium-girls-kate-moore.guide.md',
            '77-working-studs-terkel.guide.md',
            '78-into-thin-air-jon-krakauer.guide.md',
            '79-touching-the-void-joe-simpson.guide.md',
            '80-endurance-alfred-lansing.guide.md',
            '81-into-the-wild-jon-krakauer.guide.md',
            '82-tracks-robyn-davidson.guide.md',
            '83-a-walk-in-the-woods-bill-bryson.guide.md',
        ])
        ->and($books->pluck('title')->slice(13, 20)->values()->all())->toBe([
            'The Microstress Effect — Rob Cross and Karen Dillon',
            'Exercised — Daniel E. Lieberman',
            'The Body Keeps the Score — Bessel van der Kolk',
            'I’m So Effing Tired — Amy Shah',
            'Move the Body, Heal the Mind — Jennifer Heisz',
            'When It Is Darkest — Rory O’Connor',
            'Forgetting — Scott A. Small',
            'How We Learn — Benedict Carey',
            'Lost Connections — Johann Hari',
            'Life Worth Living — Miroslav Volf, Matthew Croasmun and Ryan McAnnally-Linz',
            'You Are Not Alone — Cariad Lloyd',
            'Noise — Daniel Kahneman, Olivier Sibony and Cass R. Sunstein',
            'Why We Sleep — Matthew Walker',
            'Essential Strategies for Social Anxiety — Alison McKleroy',
            'Why We Can’t Sleep — Ada Calhoun',
            'Abundance — Ezra Klein and Derek Thompson',
            'Careless People — Sarah Wynn-Williams',
            'The Let Them Theory — Mel Robbins',
            'The Intermittent Fasting Revolution — Mark P. Mattson',
            'When Breath Becomes Air — Paul Kalanithi',
        ])
        ->and($books->pluck('title')->slice(33)->values()->all())->toBe([
            'Man’s Search for Meaning — Viktor E. Frankl',
            'The Choice — Edith Eger',
            'Night — Elie Wiesel',
            'The Happiest Man on Earth — Eddie Jaku',
            'The Hiding Place — Corrie ten Boom with John and Elizabeth Sherrill',
            'Educated — Tara Westover',
            'The Glass Castle — Jeannette Walls',
            'Know My Name — Chanel Miller',
            'I Am Malala — Malala Yousafzai with Christina Lamb',
            'Long Walk to Freedom — Nelson Mandela',
            'The Autobiography of Malcolm X — Malcolm X with Alex Haley',
            'Born a Crime — Trevor Noah',
            'The Sun Does Shine — Anthony Ray Hinton with Lara Love Hardin',
            'Just Mercy — Bryan Stevenson',
            'Between the World and Me — Ta-Nehisi Coates',
            'The Warmth of Other Suns — Isabel Wilkerson',
            'When They Call You a Terrorist — Patrisse Khan-Cullors and asha bandele',
            'Being Mortal — Atul Gawande',
            'The Last Lecture — Randy Pausch with Jeffrey Zaslow',
            'Tuesdays with Morrie — Mitch Albom',
            'The Year of Magical Thinking — Joan Didion',
            'A Grief Observed — C. S. Lewis',
            'Crying in H Mart — Michelle Zauner',
            'The Diving Bell and the Butterfly — Jean-Dominique Bauby',
            'Brain on Fire — Susannah Cahalan',
            'An Unquiet Mind — Kay Redfield Jamison',
            'The Center Cannot Hold — Elyn R. Saks',
            'Maybe You Should Talk to Someone — Lori Gottlieb',
            'Do No Harm — Henry Marsh',
            'This Is Going to Hurt — Adam Kay',
            'War Doctor — David Nott',
            'The Language of Kindness — Christie Watson',
            'The Man Who Mistook His Wife for a Hat — Oliver Sacks',
            'Mountains Beyond Mountains — Tracy Kidder',
            'Evicted — Matthew Desmond',
            'Nickel and Dimed — Barbara Ehrenreich',
            'Maid — Stephanie Land',
            'Nomadland — Jessica Bruder',
            'The Immortal Life of Henrietta Lacks — Rebecca Skloot',
            'Hidden Valley Road — Robert Kolker',
            'Empire of Pain — Patrick Radden Keefe',
            'Bad Blood — John Carreyrou',
            'The Radium Girls — Kate Moore',
            'Working — Studs Terkel',
            'Into Thin Air — Jon Krakauer',
            'Touching the Void — Joe Simpson',
            'Endurance — Alfred Lansing',
            'Into the Wild — Jon Krakauer',
            'Tracks — Robyn Davidson',
            'A Walk in the Woods — Bill Bryson',
        ])
        ->and($otherUser->libraryCategories()->count())->toBe(0)
        ->and($otherUser->projects()->count())->toBe(0)
        ->and($otherUser->snippets()->count())->toBe(0);

    $books->each(function (Snippet $book): void {
        $variation = $book->variations->sole();
        $steps = (new GuideStepParser)->parse($variation->content);

        expect($book->content_type)->toBe(Snippet::CONTENT_TYPE_GUIDE)
            ->and($book->language)->toBe('markdown')
            ->and($book->folder_id)->toBeNull()
            ->and($book->tags->pluck('slug')->all())->toContain('book', 'reading-notes', 'guide')
            ->and($variation->name)->toBe('Essential learnings')
            ->and($variation->is_default)->toBeTrue()
            ->and($steps)->toHaveCount(7)
            ->and(collect($steps)->pluck('key')->duplicates()->all())->toBe([])
            ->and(collect($steps)->every(
                fn (array $step): bool => $step['title'] !== '' && $step['instructions'] !== '',
            ))->toBeTrue()
            ->and(Str::wordCount($variation->content))->toBeGreaterThanOrEqual(800)
            ->and($variation->content)->toContain('https://');
    });
});

test('rerunning the Books category seeder updates the collection without duplicates', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(BooksCategorySeeder::class);
    $this->seed(BooksCategorySeeder::class);

    $category = $user->libraryCategories()->where('name', 'Books')->sole();
    $project = Project::query()
        ->whereBelongsTo($user)
        ->whereBelongsTo($category, 'libraryCategory')
        ->where('name', 'Reading Notes')
        ->sole();

    expect($user->libraryCategories()->where('name', 'Books')->count())->toBe(1)
        ->and($user->projects()->where('name', 'Reading Notes')->count())->toBe(1)
        ->and($project->snippets()->count())->toBe(83)
        ->and($project->snippets()->withCount('variations')->get()->pluck('variations_count')->all())
        ->toBe(array_fill(0, 83, 1));
});

test('health and personality claims retain evidence cautions', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(BooksCategorySeeder::class);

    $contents = $user->projects()
        ->where('name', 'Reading Notes')
        ->sole()
        ->snippets()
        ->with('variations')
        ->get()
        ->mapWithKeys(fn (Snippet $snippet): array => [
            $snippet->filename => $snippet->variations->sole()->content,
        ]);

    expect($contents['06-breath-james-nestor.guide.md'])
        ->toContain('medical advice', 'evidence', 'nasal breathing')
        ->and($contents['11-surrounded-by-idiots-thomas-erikson.guide.md'])
        ->toContain('DISC', 'scientific', 'four fixed types')
        ->and($contents['16-the-body-keeps-the-score-bessel-van-der-kolk.guide.md'])
        ->toContain('trauma', 'evidence', 'qualified')
        ->and($contents['17-im-so-effing-tired-amy-shah.guide.md'])
        ->toContain('adrenal fatigue', 'medical advice')
        ->and($contents['19-when-it-is-darkest-rory-oconnor.guide.md'])
        ->toContain('Samaritans', '988', 'immediate danger')
        ->and($contents['22-lost-connections-johann-hari.guide.md'])
        ->toContain('antidepressants', 'evidence', 'medical advice')
        ->and($contents['26-why-we-sleep-matthew-walker.guide.md'])
        ->toContain('evidence', 'individual variation', 'medical advice')
        ->and($contents['27-essential-strategies-for-social-anxiety-alison-mckleroy.guide.md'])
        ->toContain('CBT', 'exposure', 'qualified professional')
        ->and($contents['32-the-intermittent-fasting-revolution-mark-p-mattson.guide.md'])
        ->toContain('eating disorder', 'diabetes', 'medical advice');
});
