<?php

namespace Database\Seeders;

final class WisdomBooksBatchThree
{
    /**
     * @return list<array{filename: string, title: string, description: string, tags: list<string>, content: string}>
     */
    public static function books(): array
    {
        return [
            self::evicted(),
            self::nickelAndDimed(),
            self::maid(),
            self::nomadland(),
            self::immortalLifeOfHenriettaLacks(),
            self::hiddenValleyRoad(),
            self::empireOfPain(),
            self::badBlood(),
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private static function evicted(): array
    {
        return [
            'filename' => '68-evicted-matthew-desmond.guide.md',
            'title' => 'Evicted — Matthew Desmond',
            'description' => 'A detailed reading note on eviction, housing insecurity, poverty, profit, racial inequality, family disruption, ethnography, and housing as essential infrastructure.',
            'tags' => ['non-fiction', 'housing', 'poverty', 'public-policy', 'racism', 'family'],
            'content' => <<<'GUIDE'
{!# guide-step: orientation | See eviction as a force that produces poverty #!}
**Matthew Desmond's _Evicted: Poverty and Profit in the American City_** follows tenants and landlords in Milwaukee through arrears, court filings, removals, emergency moves and repeated attempts to find another home. Desmond lived first in a predominantly white mobile-home park and then in a rooming house in a predominantly Black inner-city neighbourhood. Close observation is combined with court records and survey research, giving the book both narrative intimacy and a wider sociological argument.

Its central reversal is that eviction is not merely what happens after poverty. Losing a home can deepen poverty by consuming possessions, interrupting work and school, damaging records, separating families and forcing people to accept worse housing. A stable home is therefore not a prize awarded after every other problem is solved. It is part of the platform from which health, employment, education, parenting and civic life become possible.

The book also asks who profits when poor households devote an extreme share of income to rent. Landlords are not presented as identical villains: they face repairs, taxes, unpaid rent, regulation and danger. Yet their discretion is backed by ownership and law, while tenants often negotiate from immediate fear of homelessness. That unequal relationship is the system Desmond wants readers to see.

{!# guide-step: account | Follow housing loss through households, courts and markets #!}
The narrative centres on eight families and two landlords. Arleen searches repeatedly for a home for herself and her sons. Lamar tries to preserve a household while living with disability. Scott's nursing career and housing are entangled with addiction. The Hinkstons and other residents of the trailer park confront poor conditions, arrears and removal. Sherrena, Tobin and their staff make decisions inside a rental market where distressed property can still yield income.

Eviction appears in more forms than a courtroom order. A landlord may tell someone to leave, shut off access, refuse renewal or pressure a household into an informal move. Tenants may abandon possessions because storage and transport cost money. A filing can remain visible to future landlords even when the tenant was not formally removed. Families then face application fees, screening, overcrowding, unsafe units or shelters, all while needing an address to secure work and services.

Desmond connects these stories to gender and race. In the communities he studied, poor Black women with children carried a particularly heavy eviction burden, just as other institutions disproportionately confined poor Black men. The comparison is illuminating when treated as a finding about patterned disadvantage, not as a claim that every person's experience is interchangeable.

{!# guide-step: learnings-one | Keep the first six essential learnings #!}
1. **Housing is enabling infrastructure.** Employment, schooling, medication, sleep and family routines become harder to sustain when an address can disappear at short notice.
2. **Eviction is both consequence and cause.** Rent arrears may precede removal, but the removal itself generates costs and exclusions that intensify later poverty.
3. **The poorest households pay scarcity premiums.** Limited choices can mean high rent for unsafe units, repeated application fees, storage loss and expensive emergency transport.
4. **Formal statistics miss informal displacement.** Court records capture filings and judgments, not every pressured departure, illegal lockout or move made to avoid a case.
5. **Children experience housing policy directly.** Moves alter schools, friendships, safety, sleep and a caregiver's capacity. Instability is not an adult financial event with children merely nearby.
6. **A record can outlast the underlying dispute.** Screening systems convert a past filing, complaint or debt into a barrier across many future applications.

{!# guide-step: learnings-two | Keep the next six essential learnings #!}
7. **Scarcity changes bargaining power.** A tenant who fears shelter or street homelessness may tolerate hazards that would be unacceptable in a market with real alternatives.
8. **Landlords operate within incentives, but incentives are choices.** Costs and risk are real; so are rules that determine who bears them and whether minimum standards are enforced.
9. **Legal representation matters.** Procedures that look neutral can produce radically unequal outcomes when one side knows the system and the other lacks time, counsel or records.
10. **Poverty consumes attention.** Constant searches, calls, inspections and deadlines leave less cognitive room for work, care and long-term planning.
11. **Home has emotional and civic value.** Privacy, memory, neighbourly ties and a place to receive others are dimensions of dignity, not luxuries added after shelter.
12. **Prevention can be cheaper than cascading crisis.** Rental assistance, counsel, mediation and adequate housing supply should be evaluated against shelter, health, school and justice-system costs created by displacement.

{!# guide-step: practice | Turn the book into a housing-stability audit #!}
Map one housing problem across four levels. At the household level, identify the immediate shortfall and what would prevent removal. At the property level, record repairs, accessibility, utilities and ownership responsibilities. At the institutional level, trace notices, court dates, legal help, benefit processing and screening. At the market level, compare income, rents, vacancy and available assistance. This prevents one missed payment from being mistaken for a complete explanation.

If you manage housing, design policy or support a tenant, ask what information arrives too late, which process requires repeated proof, and where a small intervention could stop a chain of loss. Separate a landlord's legitimate claim for payment from an assumption that immediate displacement is the only remedy. Preserve written records and obtain qualified local advice because tenancy law varies.

For personal reflection, calculate housing cost as more than monthly rent: include deposits, utilities, transport, childcare effects, application fees and the value of stability. Then ask whose labour and risk make your own home dependable.

{!# guide-step: limits | Keep ethnography, evidence and policy distinct #!}
_Evicted_ is immersive ethnography focused on particular Milwaukee communities during a particular period. The people are not a statistically representative sample of every US tenant or landlord, and conditions differ across jurisdictions. Desmond supplements observation with the Milwaukee Area Renters Study; later work by the Eviction Lab greatly expanded national administrative data. Even that data has gaps because courts record cases differently and informal moves often remain invisible.

The book reconstructs painful private lives involving addiction, disability, violence, child welfare and bereavement. Readers should resist treating a participant as a case study stripped of dignity, or reducing every decision to a personal flaw. Close access gives insight but also gives an author power to select scenes and causal emphasis.

Its policy argument for making decent housing broadly affordable deserves evaluation alongside current local evidence, tenant voices, budgets and legal constraints. This guide is not legal advice. Someone facing removal should seek a qualified housing adviser, tenant organisation or lawyer rather than relying on a national book summary.

{!# guide-step: reflect | Ask what stability would make possible #!}
- Which problem described as personal failure is partly produced by housing instability?
- What costs of eviction are paid by schools, employers, hospitals, relatives and future landlords?
- Which informal displacements are absent from the data you use?
- Where does a screening rule turn a past crisis into a permanent penalty?
- What would change if stable housing were treated as a foundation rather than a reward?
- How can tenant protection and responsible property maintenance be designed together?
- Whose testimony is missing when housing debates use only prices and unit counts?

The durable chain is **scarcity → unequal bargaining power → displacement → cascading loss → narrower choices**. Effective response works earlier in the chain and restores genuine alternatives.

**Reference links:** [official Penguin Random House book record](https://www.penguinrandomhouse.com/books/247816/evicted-by-matthew-desmond/), [Princeton Eviction Lab background](https://evictionlab.org/about/), and [Eviction Lab research collection](https://evictionlab.org/research/).
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private static function nickelAndDimed(): array
    {
        return [
            'filename' => '69-nickel-and-dimed-barbara-ehrenreich.guide.md',
            'title' => 'Nickel and Dimed — Barbara Ehrenreich',
            'description' => 'A critical reading note on low-wage work, housing costs, bodily labour, surveillance, worker skill, hidden subsidies, immersive journalism, and the limits of an undercover experiment.',
            'tags' => ['non-fiction', 'journalism', 'poverty', 'workplace', 'housing', 'ethics'],
            'content' => <<<'GUIDE'
{!# guide-step: orientation | Enter low-wage work without calling it unskilled #!}
In **_Nickel and Dimed: On (Not) Getting By in America_**, Barbara Ehrenreich tests whether one worker can obtain housing, food and transport from entry-level service wages. Reporting around the time of US welfare reform, she takes jobs in three regions: restaurant and hotel work in Florida, cleaning and residential-care food service in Maine, and retail work in Minnesota. The experiment exposes the distance between praising work in principle and providing workers the conditions needed to live.

The book's sharpest correction concerns the word “unskilled.” Waiting tables, cleaning rooms, caring for residents and stocking a large store require memory, coordination, emotional restraint, pace, bodily endurance and judgment. Low pay does not measure low difficulty. It often reflects bargaining power, the invisibility of the worker to the customer, and institutions designed to make replacement seem easy.

Ehrenreich is explicit that she has advantages: a car, health, English, education, start-up money and an emergency exit. Those safeguards make her inability to create a stable budget more significant, but they also mean she cannot claim to have become poor or fully reproduced a coworker's life.

{!# guide-step: account | Follow wages into motels, shifts and exhausted bodies #!}
Each location begins with a job search and immediately becomes a housing problem. Cheap apartments may require deposits or long travel. Without enough cash to secure a lease, a worker can pay more per night for a motel, leaving less to accumulate the deposit that would lower future costs. Transport dictates which shifts are reachable; an unpredictable schedule makes a second job and childcare harder to coordinate.

At work, the official task is only part of the job. Workers absorb customer disrespect, uniform costs, drug tests, personality screening, rules against talking and constant monitoring. Managers can demand “open availability” while guaranteeing few hours. Injuries or illness threaten both income and employment, making coworkers informally cover one another where formal support is weak.

Ehrenreich repeatedly finds generosity among people with very little slack. That generosity is not evidence that deprivation creates better character. It shows workers privately subsidising an economy that does not provide adequate time, staffing or security.

{!# guide-step: learnings-one | Keep the first six essential learnings #!}
1. **A wage cannot be judged apart from local costs.** Hourly pay says little without rent, transport, food, healthcare, childcare, taxes and the number of hours actually offered.
2. **Housing converts a cash shortage into a recurring penalty.** People unable to fund a deposit may pay higher motel rates and lose the chance to cook or store food.
3. **Low-wage work is skilled embodied performance.** Speed, anticipation, courtesy and error prevention remain demanding even when job descriptions treat labour as interchangeable.
4. **Time poverty compounds money poverty.** Commuting, split shifts, applications and benefit administration consume hours that cannot be used for rest, care or advancement.
5. **Surveillance signals a trust hierarchy.** Tests, searches and scripts subject workers to suspicion while decision-makers face less scrutiny over scheduling or safety.
6. **The body balances the budget.** When income is insufficient, workers often defer sleep, medical treatment, food quality or recovery, turning financial deficits into physical ones.

{!# guide-step: learnings-two | Keep the next six essential learnings #!}
7. **Irregular hours transfer risk downward.** Employers preserve flexibility while workers absorb fluctuations in income, childcare and travel.
8. **Customer comfort can depend on worker invisibility.** Clean rooms and stocked shelves appear effortless when the labouring person is expected not to interrupt the experience.
9. **A second job is not a simple arithmetic fix.** Schedules overlap, bodies tire, travel costs rise and neither employer may tolerate limited availability.
10. **Coworker solidarity is valuable but cannot replace rights.** Shared food, rides and shift coverage help people survive while leaving the underlying shortage intact.
11. **Respect is a material workplace condition.** The ability to use a bathroom, speak, sit, know a schedule and raise a concern affects health and retention as well as dignity.
12. **Low prices can conceal displaced costs.** Customers and firms benefit when workers, families, charities or public programmes cover needs the wage does not meet.

{!# guide-step: practice | Calculate the real employment bargain #!}
For any job, build a “whole-work ledger.” On the income side, record guaranteed hours, variable hours, tips and benefits. On the cost side, record travel time and money, uniforms, equipment, meals, childcare, unpaid preparation, recovery and health risks. Add a predictability column: when is the schedule known, who controls changes, and what happens after illness? This makes visible costs that an hourly rate hides.

Managers can use the ledger to identify one repair within their control: publish schedules earlier, guarantee a minimum number of hours, pay travel between sites, allow paid sick time, remove an unnecessary surveillance practice or create a credible route for worker input. Ask employees what the job requires rather than designing from observation alone.

Readers with stable professional work can audit convenience. Who cleans, delivers, stocks or cares for the environment you rely on? Learn the conditions from workers and worker organisations before turning appreciation into advice.

{!# guide-step: limits | Respect what an undercover experiment cannot know #!}
Ehrenreich's fieldwork was brief, geographically limited and conducted by a white, highly educated journalist who could leave. She did not carry a lifetime of debt, dependants, disability, immigration constraints or racialised exposure into the experiment. Her account is vivid investigative journalism, not a representative labour survey or a proof that every employer and job operate alike.

The research is also historically situated around 1998–2000. Wage floors, housing markets, scheduling software, benefits and worker organising have changed unevenly since then. The mechanisms remain useful hypotheses, but current claims need current local data.

Undercover reporting creates ethical tension because coworkers and managers may not know their lives will inform a book. Ehrenreich changes identifying details and reflects on her advantage, but the reader should still ask who controls the story. Pair the account with first-person work by people who did not have a planned exit and with labour statistics. Do not imitate the experiment by occupying scarce services or exposing coworkers without safeguards.

{!# guide-step: reflect | Notice who subsidises everyday convenience #!}
- Which skills disappear when a job is labelled entry level?
- How much notice would you need to coordinate care, transport and a second commitment?
- What apparent bargain in your life relies on someone else's underpaid time?
- Which workplace rule protects safety, and which mainly communicates distrust?
- What expense becomes more costly because a worker cannot pay upfront?
- How would the account differ if narrated entirely by long-term coworkers?
- Which conclusion still needs updated evidence before informing policy today?

The practical chain is **low wage + high fixed costs + unstable time → repeated emergency choices**. Individual thrift cannot close every structurally negative budget.

**Reference links:** [official author and Hachette book record](https://www.barbaraehrenreich.com/external/title/9780312626686/), [Macmillan teaching guide](https://static.macmillan.com/static/macmillan/2020-online-resources/downloads/nickel-and-dimed-tg.pdf), and [US Bureau of Labor Statistics data on low-wage occupations](https://www.bls.gov/oes/).
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private static function maid(): array
    {
        return [
            'filename' => '70-maid-stephanie-land.guide.md',
            'title' => 'Maid — Stephanie Land',
            'description' => 'A detailed reading note on domestic work, single parenthood, abuse, benefits, housing insecurity, stigma, invisible labour, education, and memoir as testimony rather than a universal poverty story.',
            'tags' => ['memoir', 'poverty', 'workplace', 'housing', 'family', 'abuse'],
            'content' => <<<'GUIDE'
{!# guide-step: orientation | Read poverty as labour rather than passivity #!}
**Stephanie Land's _Maid: Hard Work, Low Pay, and a Mother's Will to Survive_** begins after she leaves an abusive relationship with her young daughter, Mia. Shelter stays, unstable housing, cleaning work and public-assistance systems replace a hoped-for path to university. The memoir follows the intense labour required simply to remain housed, fed, employed and recognised as a capable parent.

Its title names paid domestic cleaning, but the book records several overlapping jobs: cleaning other people's homes, managing benefits, searching for housing, driving between clients, caring for a child, documenting compliance and containing the fear produced by an abusive former partner. Only some of this labour appears on a payslip. The rest is treated by institutions as background, even though failure at any part can destabilise everything else.

Land writes from inside scarcity while retaining a long-term ambition to write and study. Education is not a magical escape hatch; it requires time, money, childcare and the belief that a future remains claimable. The memoir's hope is credible because it does not make endurance painless.

{!# guide-step: account | Follow a week across homes, forms and fragile shelter #!}
Cleaning clients are often identified by a feature of the house or the emotional atmosphere Land encounters. She learns intimate evidence of other lives while being expected to remain unobtrusive. A client's home may display abundance, loneliness, illness or indifference, but the worker usually has little permission to ask. The asymmetry is striking: she knows where people sleep and what they discard, while they may know almost nothing about whether she and Mia have a safe place that night.

Housing problems recur through mould, illness, temporary rooms and the difficulty of qualifying for a lease on irregular income. Benefits help, yet applications, recertification and eligibility boundaries create additional work and stigma. A small rise in earnings can reduce assistance before it produces genuine security. Cars, childcare and health become linked dependencies: a breakdown can cancel jobs, lost jobs threaten housing, and unstable housing worsens illness.

The relationship with Mia prevents poverty from becoming an abstract budget. Every decision also asks what provides safety, continuity and tenderness for a child.

{!# guide-step: learnings-one | Keep the first six essential learnings #!}
1. **Leaving abuse is a process, not a doorway.** Separation may increase financial, legal and housing danger even when it is necessary for safety.
2. **Domestic work is skilled and physically costly.** Efficient cleaning requires sequencing, product knowledge, attention, trust and repetitive bodily effort.
3. **Poverty creates administrative labour.** Forms, appointments, proof and recertification consume time precisely when time and transport are least available.
4. **Benefits can stabilise without creating security.** Food or housing support matters, yet eligibility cliffs and delays can leave a household permanently near crisis.
5. **A home can harm health.** Mould, crowding, cold and unsafe surroundings turn low rent into medical and developmental costs.
6. **Paid intimacy can coexist with social invisibility.** A cleaner enters private space but may still be treated as interchangeable or absent.

{!# guide-step: learnings-two | Keep the next six essential learnings #!}
7. **Single parenthood multiplies every constraint.** Work schedules, illness, transport and childcare cannot be solved independently when one adult holds responsibility for all of them.
8. **Stigma distorts public assistance.** Scrutiny of a small purchase can deny a recipient ordinary taste and agency while ignoring the structural shortage in the budget.
9. **Scarcity makes prevention difficult.** Maintenance, dental care, rest and bulk buying may save money later but remain inaccessible when cash is needed now.
10. **Writing can restore complexity.** Land turns experiences often reduced to stereotypes into a record of attention, anger, competence and aspiration.
11. **Help is most useful when it preserves dignity.** Reliable childcare, a safe room, a ride or clear guidance can expand choice without demanding a performance of gratitude.
12. **Resilience should indict the conditions that consume it.** Admiring survival is incomplete if preventable barriers remain untouched.

{!# guide-step: practice | Make hidden care and compliance work visible #!}
Create a seven-day time map for a household or service user. Include paid work, travel, care, cleaning, shopping, benefits administration, legal appointments, health management and recovery. Mark tasks with fixed deadlines and those requiring another organisation to respond. Then identify the single failure that would trigger the largest cascade. Support that dependency first rather than offering generic budgeting advice.

For employers of domestic workers, write down the complete agreement: scope, rate, paid travel, supplies, cancellations, breaks, illness, privacy and how concerns are raised. Pay for time and expertise rather than assuming personal warmth substitutes for fair terms.

For service design, count proof requests and repeat visits. Ask whether information can be reused, decisions explained in plain language and benefit changes phased rather than abrupt. Include recipients in redesign and compensate their expertise.

{!# guide-step: limits | Hold memoir, adaptation and social pattern apart #!}
_Maid_ is one person's retrospective account. Names and identifying details may be changed, and private relationships are narrated from Land's perspective. The television adaptation combines and invents characters and should not be used as verification of the book. Memoir communicates lived meaning; it does not estimate how frequently every experience occurs.

Land is a white US writer in a domestic-work sector disproportionately staffed by immigrant women and women of colour. Her story deserves attention without becoming the representative centre of all cleaners' lives. Pair it with domestic-worker organisations and workers writing from different immigration, racial and family positions.

The book includes domestic abuse, homelessness, child illness, degrading treatment and anxiety. Do not use it to instruct someone simply to leave an abusive situation. Safety planning should be individual and supported by qualified local services. Likewise, public-benefit rules vary by time and place; this is not legal or eligibility advice.

{!# guide-step: reflect | Ask what work remains unpaid and unseen #!}
- Which task in a low-income parent's week is mistakenly classified as free time?
- What support disappears before increased earnings create genuine stability?
- How does your workplace treat a cleaner when no customer is watching?
- Which housing saving transfers cost into health?
- What would make an offer of help increase choice rather than control?
- Whose experience of domestic work should be read alongside Land's?
- Where have you praised resilience instead of reducing the need for it?

Remember the dependency chain: **safety → housing → childcare and transport → reliable work → room for education and recovery**. A break in one link can threaten the others.

**Reference links:** [official Hachette book record](https://www.hachettebookgroup.com/titles/stephanie-land/maid/9780316505109/), [Stephanie Land's official book page](https://stepville.com/maid/), and [National Domestic Workers Alliance research and worker resources](https://www.domesticworkers.org/).
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private static function nomadland(): array
    {
        return [
            'filename' => '71-nomadland-jessica-bruder.guide.md',
            'title' => 'Nomadland — Jessica Bruder',
            'description' => 'A detailed reading note on older vehicle dwellers, seasonal labour, retirement insecurity, mobile community, bodily cost, housing, freedom, precarity, and the ethics of immersive reporting.',
            'tags' => ['non-fiction', 'journalism', 'housing', 'poverty', 'workplace', 'travel'],
            'content' => <<<'GUIDE'
{!# guide-step: orientation | Keep freedom and compulsion in the same frame #!}
**Jessica Bruder's _Nomadland: Surviving America in the Twenty-First Century_** follows older Americans living in vans, cars and recreational vehicles while travelling between temporary jobs. Many lost housing or retirement security after recession, medical expense, divorce or stagnant earnings. On the road they create practical networks, vocabularies and gatherings that refuse the idea that losing a conventional home means losing community.

Bruder's account resists a simple choice between romantic adventure and homelessness. Some people value mobility and reject possessions; some would prefer stable affordable housing; many experience both liberation and constraint. A vehicle can be home, transport and last asset at once. Its breakdown can therefore erase shelter and income together.

The book is immersive journalism. Bruder buys a van, joins gatherings and works seasonal jobs alongside participants, especially Linda May. Participation lets her describe warehouse pace and road routines, but she remains a reporter with an apartment, salary, institutional support and an exit.

{!# guide-step: account | Follow workampers through seasonal circuits #!}
The workers move among Amazon fulfilment centres, sugar-beet harvests, campgrounds and other seasonal employers. Programmes recruit people who bring their own housing, allowing companies to draw on labour without building a settled workforce. Employers may provide a campsite or recruitment community, but workers still absorb fuel, vehicle, weather and relocation risk.

Linda May becomes the narrative anchor. Her hope of building an inexpensive Earthship home gives the road a destination while repeated work keeps that destination difficult to reach. Bob Wells and gatherings such as the Rubber Tramp Rendezvous show mobile people teaching one another repairs, parking etiquette, power systems and survival strategies. Community is real, yet its existence should not be used to claim that inadequate pensions or unaffordable rent no longer matter.

Bodies register the labour bargain. Long shifts on concrete, repetitive movement and limited recovery are especially consequential for older workers. The road creates beautiful encounters and chosen relationships, but it does not stop ageing.

{!# guide-step: learnings-one | Keep the first six essential learnings #!}
1. **Mobility can be both agency and adaptation.** Choosing how to live within constrained options is meaningful, even when the options themselves are unjustly narrow.
2. **A vehicle concentrates risk.** Mechanical failure may simultaneously threaten home, transport, stored possessions and access to the next job.
3. **Employers can externalise housing costs.** A mobile workforce supplies its own shelter and relocation while the company purchases labour only for peak demand.
4. **Retirement is an institution, not an age.** Savings, pensions, healthcare, housing and labour history determine whether later life contains rest or continued necessity.
5. **Community is portable when people practise it.** Shared knowledge, warnings, tools and reunion points create belonging without a fixed neighbourhood.
6. **Rebranding can protect dignity and obscure need.** Terms such as workamper or houseless may express identity while public systems still need to recognise material insecurity.

{!# guide-step: learnings-two | Keep the next six essential learnings #!}
7. **Seasonal flexibility is asymmetrical.** Firms gain labour on demand; workers carry uncertain hours, travel expense and gaps between seasons.
8. **Bodies expose the limit of motivational stories.** Positive attitude cannot remove repetitive strain, heat, cold, fatigue or reduced access to care.
9. **Public land functions as an informal safety net.** Parking and camping rules can determine whether someone has a lawful place to sleep, yet access is inconsistent and politically contested.
10. **Ingenuity is not proof of adequate policy.** Solar panels, stealth parking and repairs demonstrate competence while also revealing how much survival work has shifted onto individuals.
11. **Home is more than ownership and more than sentiment.** It combines safety, privacy, continuity, legal standing and a base for relationships; different arrangements meet these needs unevenly.
12. **The dream of escape can coexist with exploitation.** A beautiful road and a harmful workplace are not mutually exclusive facts.

{!# guide-step: practice | Audit where flexibility transfers cost and danger #!}
Take a seasonal or gig-work arrangement and list what the employer supplies versus what the worker supplies: recruitment, training, equipment, travel, housing, utilities, insurance, downtime and injury recovery. Add who benefits when demand changes and who pays after a cancellation. A flexible arrangement is fairer when risks and gains are shared rather than merely renamed.

For services, test every requirement against vehicle dwelling. Can someone receive mail, maintain identification, access sanitation, vote, store medicine and obtain healthcare without a fixed address? Invite people with lived experience to identify rules that accidentally turn mobility into exclusion.

For personal decisions about van life, build two plans rather than an aesthetic mood board: a desired-life plan and a failure plan covering repairs, extreme weather, illness, safe parking, income gaps and an exit from driving. Do not assume a book or film is safety instruction.

{!# guide-step: limits | Resist both spectacle and romantic rescue #!}
The book concentrates on a network of predominantly white, older vehicle dwellers observed in the 2010s. It cannot represent every unhoused person, migrant worker, retiree, van-life influencer or person who chooses nomadism with substantial assets. Race, disability, immigration status, family responsibilities and policing change how safe mobility can be.

Bruder selects and arranges years of reporting into a narrative. Her participation builds trust but does not erase the economic difference between temporary research discomfort and having no secure fallback. Readers should not visit, photograph or locate participants as tourist attractions.

The award-winning film is an adaptation with fictionalisation and a different emphasis; use the book and sources rather than treating film scenes as documentary proof. Labour programmes, parking rules and economic conditions also change, so current practical claims need current verification.

{!# guide-step: reflect | Decide whether a system offers mobility or merely motion #!}
- Which part of mobile life is freely chosen, and which part responds to missing alternatives?
- What does an employer avoid paying when workers bring their homes?
- How would one vehicle repair affect shelter, income and safety at once?
- Which form of community travels well, and which public service still assumes a fixed address?
- Where does an uplifting story hide bodily cost?
- Whose experience is absent from a predominantly older white network?
- What would dignified later life require beyond individual resourcefulness?

The central tension is **self-authored mobility within structurally restricted choices**. Preserve both sides and policy becomes more honest.

**Reference links:** [Jessica Bruder's official site](https://www.jessicabruder.com/), [official W. W. Norton book record](https://wwnorton.com/books/9780393356311), and [US Government Accountability Office work on retirement security](https://www.gao.gov/retirement-security).
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private static function immortalLifeOfHenriettaLacks(): array
    {
        return [
            'filename' => '72-the-immortal-life-of-henrietta-lacks-rebecca-skloot.guide.md',
            'title' => 'The Immortal Life of Henrietta Lacks — Rebecca Skloot',
            'description' => 'A detailed reading note on Henrietta Lacks, HeLa cells, consent, medical racism, family, scientific benefit, privacy, commercialisation, narrative journalism, and ethical research relationships.',
            'tags' => ['non-fiction', 'medicine', 'healthcare', 'ethics', 'racism', 'family'],
            'content' => <<<'GUIDE'
{!# guide-step: orientation | Put the person before the cell line #!}
**Rebecca Skloot's _The Immortal Life of Henrietta Lacks_** joins scientific history to the life of a Black woman whose name was long missing from that history. Henrietta Lacks was treated for cervical cancer at Johns Hopkins Hospital in 1951. Cells taken from her tumour without her knowledge or consent continued dividing in culture. Named HeLa from letters in her name, they became an exceptionally influential research tool used across biomedical science.

The book does not ask readers to choose between gratitude for research and concern for the person whose tissue enabled it. It asks them to hold both. HeLa cells contributed to major scientific advances and an enormous research infrastructure. Henrietta nevertheless died young, her children grew up without her, and relatives later encountered scientists, reporters and institutions from a position marked by poverty, medical mistreatment and incomplete information.

The title's “immortal” cells can tempt readers to turn Henrietta into a miracle object. Skloot's corrective is biographical: she was a daughter, wife, mother, tobacco farmer and patient. Ethical memory begins by restoring the human life that the laboratory abbreviation obscured.

{!# guide-step: account | Follow one family's encounter with medicine and history #!}
Skloot reconstructs Henrietta's childhood in Clover, Virginia, her move to Baltimore, her illness and treatment, and the circulation of her cells after death. She also follows the later lives of Henrietta's children. Deborah Lacks becomes central to the reporting relationship. She wants to understand what happened to her mother and sister, what cells are, whether experiments could somehow hurt the family, and why others appeared to benefit while the family struggled to obtain healthcare.

Confusion is not presented as stupidity. Technical language, poor communication, inconsistent stories and a history of racist medical abuse made mistrust rational. Researchers approached family members for blood samples in the 1970s, but relatives did not consistently understand the purpose. Public release of family-linked genetic information later exposed another problem: a specimen can be de-identified on paper while genomic data remains connected to relatives who never donated it.

The narrative moves through cell-culture science, commercial supply, debates about ownership and the institutionalisation of research ethics. It distinguishes the standards common in 1951 from the stronger consent expectations developed later without implying that common practice was therefore fair. It also avoids the inaccurate shortcut that Johns Hopkins personally made a fortune from HeLa; the institution states that its scientists did not sell or profit from the discovery, even though a wider commercial market developed around biological materials.

{!# guide-step: learnings-one | Keep the first six essential learnings #!}
1. **Scientific material comes from a human relationship.** A tissue sample is biologically useful because someone lived, became ill, entered care and entrusted a system with access to their body.
2. **Benefit does not cancel the need for consent.** Research can produce extraordinary public value while the way material was obtained or information communicated remains ethically inadequate.
3. **Historical context explains practice without settling morality.** Consent rules differed in 1951, but legality or custom alone cannot answer whether a patient was treated with respect.
4. **Genomic privacy is relational.** Information derived from one person's cells can reveal facts about parents, siblings and descendants who made no research decision.
5. **Mistrust can be evidence-based.** Racist experimentation, segregated care and dismissive encounters shape how later requests are understood; rebuilding trust requires conduct, not reassurance alone.
6. **Language distributes power.** Terms such as culture, clone and immortality are ordinary to researchers but can become frightening when families receive them without patient explanation.

{!# guide-step: learnings-two | Keep the next six essential learnings #!}
7. **Recognition is not the same as compensation or control.** Naming Henrietta corrects erasure, but distinct questions remain about decision rights, commercial benefit and access to care.
8. **Families are not obstacles to science.** The Lacks family's later participation in governance shows that meaningful involvement can support research while setting boundaries around data.
9. **Ethics must continue after collection.** Storage, sharing, sequencing, publication and commercial reuse create decisions that an original consent form may never have anticipated.
10. **Narrative can connect systems that specialist accounts separate.** Family history, laboratory practice, law, race and commerce become one ethical story rather than isolated subjects.
11. **Good intentions do not repair informational asymmetry.** A researcher may seek valuable knowledge, yet still owe a participant clear purpose, alternatives, risks and an opportunity to refuse.
12. **Scientific gratitude should remain specific.** Honour the contribution without claiming Henrietta knowingly volunteered, romanticising suffering or treating one family as responsible for every later use.

{!# guide-step: practice | Build consent as an ongoing relationship #!}
For a research, data or product project, draw the full lifecycle of what is collected. Record who provides it, what they are told, where it is stored, who can receive it, what new inferences may become possible and how withdrawal or future contact works. Add relatives or communities who may be affected even though they are not formal participants. A signature at collection is one checkpoint, not the entire ethical system.

Rewrite a technical explanation for three audiences: a specialist, an adult without domain knowledge and a family member learning about use years later. Ask someone from each audience to explain it back in their own words. Comprehension, not document delivery, is the useful test.

When telling a history of innovation, perform a credit audit. Name the people who supplied bodily material, care, technical labour, public funding and risk, alongside prominent investigators. Specify which claims concern discovery, distribution, profit or ownership so that a compelling moral story does not become a factual overstatement.

{!# guide-step: limits | Separate narrative evidence, law and present-day practice #!}
This is deeply researched narrative nonfiction built from interviews, records, scientific literature and Skloot's relationship with members of the Lacks family. Reconstructed scenes and reported memories remain selected and shaped by an author. No single family member speaks for every relative, and the family's concerns should not be simplified into opposition to science.

The book spans changing legal and ethical regimes. Tissue ownership, consent and genomic-data rules vary by jurisdiction and have continued to evolve since publication in 2010. In 2013, the US National Institutes of Health reached an agreement with members of the Lacks family giving them participation in decisions about access to certain HeLa genomic data. That important development is not a universal solution for all biospecimens.

Descriptions include cancer, death, racism, abuse, institutionalisation and family trauma. Use care when teaching them and do not turn private suffering into a dramatic laboratory anecdote. This guide is neither legal advice nor a claim that today's research systems have solved inequity. Current projects require current regulation, community involvement and qualified ethics review.

{!# guide-step: reflect | Ask who can understand and govern the afterlife of data #!}
- When does a specimen stop appearing connected to the person who supplied it?
- Which future use would a participant be surprised to learn about?
- Who benefits, who is recognised and who has meaningful decision power?
- What history might make a community reasonably cautious about your request?
- Does your consent process test understanding or merely collect a signature?
- Which relatives could be affected by one person's genomic disclosure?
- How would you describe a scientific contribution without erasing the contributor's ordinary life?

The enduring sequence is **human life → tissue and data → discovery and circulation → benefits and new obligations**. Ethical science keeps the first term visible throughout the rest.

**Reference links:** [Rebecca Skloot's official book page](https://rebeccaskloot.com/the-immortal-life/), [Johns Hopkins account of Henrietta Lacks and HeLa](https://www.hopkinsmedicine.org/henrietta-lacks/immortal-life-of-henrietta-lacks), [Johns Hopkins bioethics commitments and historical clarifications](https://www.hopkinsmedicine.org/henrietta-lacks/upholding-the-highest-bioethical-standards), and [NIH description of the Lacks family genomic-data agreement](https://www.nih.gov/news-events/news-releases/nih-lacks-family-reach-understanding-share-genomic-data-hela-cells).
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private static function hiddenValleyRoad(): array
    {
        return [
            'filename' => '73-hidden-valley-road-robert-kolker.guide.md',
            'title' => 'Hidden Valley Road — Robert Kolker',
            'description' => 'A detailed reading note on the Galvin family, schizophrenia, genetics, caregiving, sibling trauma, psychiatric history, scientific uncertainty, stigma, and the ethics of turning family experience into evidence.',
            'tags' => ['non-fiction', 'mental-health', 'family', 'medicine', 'trauma', 'ethics'],
            'content' => <<<'GUIDE'
{!# guide-step: orientation | Hold family testimony and scientific uncertainty together #!}
**Robert Kolker's _Hidden Valley Road: Inside the Mind of an American Family_** tells the story of Don and Mimi Galvin and their twelve children, six of whose sons were diagnosed with schizophrenia. It interweaves decades of family experience with changing scientific attempts to understand psychosis. The Galvins became valuable to researchers because an unusually high concentration of illness within one family could help test genetic hypotheses.

The book's wisdom lies partly in refusing a clean answer. Schizophrenia is not explained by one defective gene, one parenting style or one traumatic event. Contemporary evidence points to complex interaction among many genetic variants, development and environment. A family can advance knowledge without becoming a universal model of cause.

Kolker also keeps scientific progress from eclipsing domestic consequence. Illness affects the diagnosed person, but fear, care, secrecy, divided attention and institutional decisions shape siblings and parents too. Each family member experiences a different household; the shared surname does not create a single shared account.

{!# guide-step: account | Follow one household through changing eras of psychiatry #!}
Don and Mimi initially imagine an accomplished, adventurous postwar family. As sons develop delusions, disorganisation and altered behaviour, the household changes. Hospital admissions, medication, conflict and attempts at ordinary life recur. The two youngest daughters, Margaret and Lindsay, carry both love and exposure to experiences adults failed to make safe. Their later perspectives complicate a story once organised mainly around the sons' diagnoses and their mother's endurance.

Alongside the family, Kolker traces research from theories that blamed mothers to biological psychiatry, twin studies, genetic linkage, neuroscience and newer accounts of multiple interacting risks. The Galvin family's blood samples and medical history enter this research world. Scientists find leads, lose confidence in simple explanations and continue building knowledge from partial results.

The account contains psychosis, suicide, sexual abuse, violence, institutionalisation and painful caregiving decisions. These are facts within this family's story, not permission to equate schizophrenia with violence. Most people with schizophrenia are not violent, and they are often more vulnerable to victimisation, exclusion and poor health than the public stereotype recognises.

{!# guide-step: learnings-one | Keep the first six essential learnings #!}
1. **Complex illness resists a single-cause story.** Genetic vulnerability can matter greatly without functioning as destiny or eliminating developmental and social influence.
2. **A diagnosis does not replace a person.** Symptoms, temperament, ability, humour, relationships and hopes differ across siblings even when a clinical label is shared.
3. **Blame rushes into explanatory gaps.** The discredited “schizophrenogenic mother” idea harmed families by converting scientific uncertainty into confident moral judgment.
4. **Every sibling inhabits a different family.** Birth order, timing of illness, exposure, escape routes and parental attention produce distinct memories of one home.
5. **Caregiver devotion can coexist with harmful decisions.** Love does not guarantee safety, accurate interpretation or enough capacity to meet every child's needs.
6. **Research value does not erase participant vulnerability.** A scientifically unusual family still deserves understandable communication, privacy and respect for differing wishes.

{!# guide-step: learnings-two | Keep the next six essential learnings #!}
7. **Scientific progress is often a chain of corrected models.** A study that fails to identify one decisive gene can still narrow hypotheses and improve later methods.
8. **Secrecy protects reputation by transferring cost inward.** What a family cannot discuss publicly is often carried privately as confusion, shame and isolation.
9. **Treatment history contains both help and injury.** Medication and hospital care may reduce acute symptoms, while side effects, coercion and fragmented systems create additional burdens.
10. **Safety and compassion are compatible obligations.** Taking psychosis seriously does not require dehumanising a person, and respecting a person does not require denying risk in a specific crisis.
11. **Unaffected relatives still need care.** Siblings may experience trauma, parentification, guilt and grief even when institutional attention centres on the diagnosed member.
12. **Uncertainty should produce humility, not passivity.** Families and clinicians can make the safest available decision while acknowledging what they do not know and revising as evidence changes.

{!# guide-step: practice | Replace labels with a support and uncertainty map #!}
For a complex health or family problem, create three columns: observations, interpretations and unknowns. Put directly witnessed changes in the first; diagnostic or causal hypotheses in the second; and missing information in the third. This reduces the chance that a frightening interpretation is repeated as fact. Add who is responsible for reassessment and what evidence would change the plan.

Map support around every affected person, not only the one with the formal diagnosis. Include crisis contacts, routine clinical care, housing, medication review, sibling support, respite and routes for reporting abuse. Identify which family member has quietly become coordinator and what would happen if that person could not continue.

In research or storytelling, ask participants what recognition, anonymity and future contact mean to them. A family may disagree internally. Ethical involvement allows more than one answer and makes clear that declining publicity is not declining the value of science.

{!# guide-step: limits | Do not turn a singular family into a clinical template #!}
_Hidden Valley Road_ is narrative nonfiction based on interviews, family material, medical history and scientific reporting. Family members remember events differently, and the author necessarily selects which voices and scenes organise the account. Private medical and traumatic experiences require respect beyond curiosity.

The Galvins' concentration of schizophrenia made the family scientifically informative but statistically unusual. Their history cannot establish the cause, likely course or best treatment for another person's symptoms. Schizophrenia is a heterogeneous clinical category, and knowledge continues to change. The book is not diagnostic or treatment advice; urgent concerns require qualified mental-health care and local crisis support.

Readers should also resist a familiar cultural error: using exceptional episodes to imply that people with schizophrenia are inherently dangerous. Discuss the specific conduct and context described, not a whole population. The book includes abuse and failures to protect children; compassion for severe illness must never require silence about another person's safety.

{!# guide-step: reflect | Make room for more than one family truth #!}
- Which conclusion is an observation, and which is a causal interpretation?
- Who received care in the family, and who was expected to adapt without it?
- What shame was created by public stigma rather than by the illness itself?
- How can a safety boundary preserve rather than deny a person's humanity?
- Which old explanation felt certain mainly because alternatives were unavailable?
- What does a research participant deserve after supplying valuable data?
- Whose memory complicates the most convenient version of the family story?

The useful posture is **take suffering seriously, hold causes modestly, protect each person specifically, and revise with evidence**.

**Reference links:** [official Penguin Random House book record](https://www.penguinrandomhouse.com/books/566406/hidden-valley-road-by-robert-kolker/), [Robert Kolker's official book page](https://robertkolker.com/hidden-valley-road), and [US National Institute of Mental Health overview of schizophrenia](https://www.nimh.nih.gov/health/topics/schizophrenia).
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private static function empireOfPain(): array
    {
        return [
            'filename' => '74-empire-of-pain-patrick-radden-keefe.guide.md',
            'title' => 'Empire of Pain — Patrick Radden Keefe',
            'description' => 'A detailed reading note on the Sackler dynasty, Purdue Pharma, OxyContin, opioid marketing, institutional incentives, reputation, accountability, investigative evidence, and responsible discussion of pain and addiction.',
            'tags' => ['non-fiction', 'journalism', 'medicine', 'ethics', 'healthcare', 'public-policy'],
            'content' => <<<'GUIDE'
{!# guide-step: orientation | Study a crisis as an incentive system #!}
**Patrick Radden Keefe's _Empire of Pain: The Secret History of the Sackler Dynasty_** is an investigative history of a family fortune, pharmaceutical marketing and the opioid crisis. It connects the Sacklers' earlier success in medical advertising to Purdue Pharma's promotion of OxyContin and to later efforts to contain legal, financial and reputational consequences.

The book's deepest lesson is not that one persuasive product caused every opioid death. It is that a harmful trajectory can emerge when commercial ambition, selective evidence, professional authority, regulatory weakness, sales incentives and institutional prestige reinforce one another. Responsibility remains specific, but the system matters because many gatekeepers had opportunities to demand better evidence or change course.

Keefe also examines philanthropy as reputation infrastructure. Donations supported genuine cultural and educational institutions while prominent naming helped separate public esteem from the source of wealth. The ethical question is broader than whether a gift funds something good: what legitimacy does the recipient lend in return, and what scrutiny disappears when gratitude governs the relationship?

{!# guide-step: account | Follow marketing claims into medicine, law and public memory #!}
The narrative begins before Purdue, with Arthur Sackler's work linking pharmaceutical promotion, physicians and medical media. Later generations launch OxyContin, an extended-release opioid, and build an aggressive sales operation around it. Sales representatives, speaker programmes and messaging to prescribers expand use. The book documents how the risk of addiction was minimised or presented with confidence beyond the supporting evidence.

As misuse, addiction and deaths grow, patients, families, journalists, prosecutors and state attorneys general seek records and accountability. Settlements can provide money or restrictions while confidentiality and carefully worded resolutions limit public understanding. Corporate structures can separate wealth, management control and personal liability. Purdue's 2020 federal guilty plea to fraud and kickback conspiracies is an important documented endpoint, but litigation and bankruptcy developments continued after the narrative's central period.

The human cost must not be reduced to corporate strategy. People with severe pain need competent, individualised care. People with opioid use disorder have a treatable health condition, not a moral defect. An ethical account can criticise deceptive promotion without stigmatising prescribed patients, bereaved families or evidence-based medication treatment for addiction.

{!# guide-step: learnings-one | Keep the first six essential learnings #!}
1. **Marketing can shape the evidence environment.** Sponsorship, sales training, publication strategy and repeated talking points influence what professionals perceive as settled knowledge.
2. **A precise-sounding reassurance still requires a traceable source.** Claims about addiction risk matter enormously; repetition and authority cannot substitute for appropriate studies.
3. **Incentives operate through ordinary decisions.** Targets, bonuses, formularies, conference invitations and prestige can align behaviour without a single explicit conspiracy.
4. **Regulatory approval is a floor, not moral immunity.** A lawful label or authorised product does not end duties to monitor harm, communicate uncertainty and correct misuse of claims.
5. **Corporate separation can fragment accountability.** Ownership, board control, operating decisions and extracted profits may sit in different legal compartments even when strategically connected.
6. **Delay is an active outcome.** Protracted litigation, sealed records and procedural complexity can preserve money and reputation while injured communities wait.

{!# guide-step: learnings-two | Keep the next six essential learnings #!}
7. **Philanthropy has a return flow.** Institutions receive funds; donors may receive naming, access, social legitimacy and insulation from questions about how wealth was accumulated.
8. **Exceptional sales growth can be a safety signal.** When a high-risk product expands rapidly, success metrics should trigger stronger surveillance rather than automatic celebration.
9. **Gatekeepers have independent duties.** Clinicians, distributors, boards, regulators, media and museums cannot outsource judgment entirely to a manufacturer or donor.
10. **Documents change public accountability.** Internal emails, depositions and sales material can reveal gaps between public language and private priorities that aggregated outcomes cannot explain.
11. **Addiction policy must avoid stigma.** Blaming patients narrows treatment access and distracts from commercial, clinical and social conditions that shape exposure and recovery.
12. **Remedy should reach the harmed system.** Financial penalties matter, but prevention, treatment, transparent records and changed incentives determine whether accountability alters future conduct.

{!# guide-step: practice | Trace a high-stakes claim from source to reward #!}
Choose a consequential health or product claim and build an evidence chain. Write the exact proposition, source study, population, outcome, uncertainty and date. Then list every place the wording becomes stronger: internal training, sales material, professional education, media and customer conversation. Mark who is rewarded when adoption rises and who observes harm after launch.

For boards and institutions, create a conflict-and-reputation register that includes more than legal conflicts. Record naming rights, donor access, sponsored research, speaker payments, bonus structures and relationships that could make questioning costly. Assign an independent owner to review contrary evidence and publish how material concerns are resolved.

When discussing opioids, use person-first, clinically accurate language. Separate pain treatment, physical dependence, misuse, opioid use disorder and overdose. Include evidence-based treatment and harm reduction in the response rather than presenting punishment as the only form of accountability.

{!# guide-step: limits | Distinguish documented facts, allegations and later events #!}
_Empire of Pain_ is investigative narrative supported by extensive endnotes, court records, interviews and prior reporting. Its structure and character focus make complex history readable, but they also represent an author's selection and interpretation. Readers should distinguish conduct established by records or admissions from allegations resolved without admission and from Keefe's inference about motive.

The opioid crisis has multiple waves and causes, including prescribing, illicit heroin and potent synthetic opioids such as fentanyl. A history centred on Purdue and the Sacklers cannot represent every company, clinician, patient or period. Legal settlements, bankruptcy rulings and public-health figures have also changed since the book's 2021 publication; verify any current number or legal status.

Accounts of addiction, overdose and bereavement require care. Do not use the book to advise an individual to stop medication abruptly or to shame someone receiving opioid treatment. Clinical decisions belong with qualified professionals, and urgent overdose risk requires current local emergency and harm-reduction guidance.

{!# guide-step: reflect | Ask where confidence outran evidence #!}
- What was the exact evidence behind the most consequential reassurance?
- Which reward encouraged expansion while another party absorbed the downside?
- Who could see emerging harm, and what made escalation difficult?
- What legitimacy did a cultural institution return to its donor?
- Which legal resolution produced payment without public understanding?
- How can accountability avoid harming pain patients or people with addiction?
- What remedy would change incentives rather than only close a case?

The warning pattern is **authoritative claim → aligned incentives → rapid adoption → suppressed friction → distributed harm → delayed accountability**. Intervene before growth becomes proof of safety.

**Reference links:** [official Penguin Random House book record](https://www.penguinrandomhouse.com/books/612715/empire-of-pain-by-patrick-radden-keefe/), [US Department of Justice account of Purdue Pharma's 2020 guilty plea](https://www.justice.gov/opa/pr/opioid-manufacturer-purdue-pharma-pleads-guilty-fraud-and-kickback-conspiracies), and [US Centers for Disease Control and Prevention opioid-overdose overview](https://www.cdc.gov/overdose-prevention/about/understanding-the-opioid-overdose-epidemic.html).
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private static function badBlood(): array
    {
        return [
            'filename' => '75-bad-blood-john-carreyrou.guide.md',
            'title' => 'Bad Blood — John Carreyrou',
            'description' => 'A detailed reading note on Theranos, health-technology claims, secrecy, governance, whistleblowers, validation, patient risk, investigative journalism, charisma, and the limits of a pre-trial narrative.',
            'tags' => ['non-fiction', 'journalism', 'technology', 'healthcare', 'ethics', 'leadership'],
            'content' => <<<'GUIDE'
{!# guide-step: orientation | Require health claims to survive independent verification #!}
**John Carreyrou's _Bad Blood: Secrets and Lies in a Silicon Valley Startup_** reconstructs the rise and collapse of Theranos. Founder Elizabeth Holmes promised that a small blood sample could support a broad menu of fast, inexpensive tests through proprietary technology. The vision attracted prominent investors, political figures, media attention, major board members and a retail partnership. Inside the company, however, technical limitations, secrecy and pressure prevented claims from being tested with the openness appropriate to patient care.

The story is often framed as charisma deceiving powerful people. That is true but incomplete. Investors and partners also wanted access to a transformative opportunity before rivals. A celebrated founder narrative, fear of missing out and misplaced confidence in elite networks lowered the demand for diagnostic expertise. Governance failed not because nobody was intelligent, but because status and urgency were treated as substitutes for relevant evidence.

The essential boundary is between ordinary start-up iteration and health deployment. A buggy consumer feature may inconvenience a user; an inaccurate laboratory result can alter medication, pregnancy decisions, cancer investigation and trust in care. “Move fast” must change meaning when error reaches a patient's body.

{!# guide-step: account | Follow secrecy from laboratory concern to public exposure #!}
Carreyrou traces Theranos from university-origin mythology through fundraising, board recruitment and the Walgreens relationship. The company tightly compartmentalises teams and invokes trade secrecy. Employees may know that one part is failing without seeing the evidence needed to understand the whole. Conventional commercial analysers are used for some tests while public messaging centres on Theranos devices and tiny samples.

Laboratory professionals and employees raise concerns about accuracy, quality control and what patients are being told. Erika Cheung, Tyler Shultz and former laboratory director Adam Rosendorff are among the people whose evidence becomes central. Speaking carries legal, financial and personal risk. Shultz's concern also creates conflict with his grandfather George Shultz, a prominent Theranos director, showing how loyalty and status can override a warning even within a family.

Carreyrou and _The Wall Street Journal_ verify claims through documents, sources and patient experience despite extensive legal pressure. Regulatory scrutiny follows, and the company's public story unravels. The book was published in 2018 before later criminal trials and convictions, so its endpoint should not be confused with the complete legal history.

{!# guide-step: learnings-one | Keep the first six essential learnings #!}
1. **Extraordinary convenience claims need proportionate validation.** Smaller samples and faster results are valuable only if accuracy, precision and clinical usefulness are demonstrated for each intended test.
2. **Secrecy can protect invention or prevent correction.** The ethical distinction is whether qualified independent people can inspect safety-critical evidence and act on concerns.
3. **A prestigious board is not necessarily a competent board.** Political, military or financial stature cannot replace laboratory, regulatory and patient-safety expertise.
4. **Partnership is not independent proof.** One famous investor or retailer may infer diligence from another, creating a loop in which nobody performs the missing verification.
5. **Mission language can intensify denial.** If criticism is framed as opposition to helping patients, employees lose the ability to identify problems as part of the mission.
6. **Compartmentalisation weakens collective reality.** Teams restricted to narrow information cannot connect repeated anomalies into a system-level warning.

{!# guide-step: learnings-two | Keep the next six essential learnings #!}
7. **Whistleblowing is an organisational stress test.** The treatment of a technically grounded dissenter reveals whether truth or hierarchy has practical authority.
8. **A demonstration is not validation.** Curated examples, prototypes and charismatic presentations cannot establish performance across real samples and clinical conditions.
9. **Health products have multiple customers and one patient.** Investors, partners and clinicians make decisions, but the person receiving a result bears consequences they may never see.
10. **Legal aggression can create temporary silence, not accuracy.** Threats may slow reporting while leaving the underlying technical fact unchanged.
11. **Journalism can function as public safety infrastructure.** Source protection, document corroboration and editorial resistance to intimidation can reveal evidence formal governance missed.
12. **Fraud stories should improve systems, not merely identify a villain.** Better boards, validation gates, regulator access and protected escalation matter more than confidence that one can spot a deceptive personality.

{!# guide-step: practice | Install evidence gates before scale amplifies error #!}
For a high-stakes product, create a claim-to-evidence table. Each external claim should name its validation dataset, comparator, error bounds, responsible technical owner, independent reviewer and approved wording. If the evidence is missing, narrow the claim or stop deployment. Do not let a successful funding round change the table.

Design escalation so an employee can bypass the manager implicated in a concern. Record what evidence was supplied, prohibit retaliation, set a response deadline and require the board's relevant expert to review unresolved safety issues. Track whether dissenters leave; turnover among quality staff is itself a risk signal.

Before a partnership, ask the partner to demonstrate the product under ordinary conditions with independently selected samples. Verify regulatory status directly rather than through marketing language. For health technology, include laboratory scientists, clinicians, statisticians and patients in diligence alongside commercial leaders.

{!# guide-step: limits | Keep the reporting timeline and wider lessons accurate #!}
_Bad Blood_ is investigative journalism based on interviews, documents, litigation and Carreyrou's own reporting. Confidential-source work may prevent readers from independently seeing every underlying item, and narrative form foregrounds particular characters. Later court proceedings created a larger evidentiary record than the 2018 book could contain. Use current official records for current legal status.

Theranos should not become evidence that every ambitious start-up, proprietary system or young founder is fraudulent. Nor should the story be used to suggest women entrepreneurs merit special suspicion. The useful generalisation concerns governance and verification: anyone making safety-critical claims should meet the same evidence standard regardless of charisma, identity or mission.

Patients whose results appear in the story are not plot devices. Avoid repeating identifiable health details unnecessarily. This guide cannot evaluate a laboratory result or medical decision; anyone concerned about testing should consult a qualified clinician and an appropriately accredited laboratory.

{!# guide-step: reflect | Find the point where diligence became deference #!}
- Which claim could an independent reviewer actually reproduce?
- What relevant expertise was missing despite an impressive list of names?
- Did confidentiality protect an invention, or block safety information?
- Who bore the consequence when a test result was wrong?
- What happened to the first person who raised a well-supported concern?
- Which partner assumed another institution had completed diligence?
- What control would still work if the founder were exceptionally persuasive?

The governance rule is simple: **the higher the human consequence, the less prestige, urgency or secrecy may substitute for reproducible evidence**.

**Reference links:** [official Penguin Random House book record](https://www.penguinrandomhouse.com/books/549478/bad-blood-by-john-carreyrou/), [US Securities and Exchange Commission 2018 enforcement announcement](https://www.sec.gov/newsroom/press-releases/2018-41), and [US Food and Drug Administration overview of laboratory-developed tests](https://www.fda.gov/medical-devices/in-vitro-diagnostics/laboratory-developed-tests).
GUIDE,
        ];
    }
}
