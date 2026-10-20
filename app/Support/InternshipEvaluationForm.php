<?php

namespace App\Support;

/**
 * Form 13 - Internship Evaluation Form (FM-AA-INT-13), completed by the
 * host company / training supervisor.
 *
 * The eight competencies, their weights and the five rating descriptors come
 * straight from the official form, so the supervisor sees exactly what a 5, 4,
 * 3, 2 or 1 means for each competency, and the printout uses the same text.
 *
 * Keys match the field keys seeded for EVALUATION_FORM ("rating_<key>").
 */
class InternshipEvaluationForm
{
    public const COMPETENCIES = [
        'ability_to_learn' => [
            'letter' => 'A',
            'name' => 'Ability To Learn',
            'weight' => 10,
            'levels' => [
                5 => 'Grasps new ideas quickly, can readily apply knowledge to new situations, flexible problem solver',
                4 => 'Learns quickly, applies past experiences',
                3 => 'Usually understands instructions; usually has good judgment and reasoning',
                2 => 'Slow to grasp concepts, sometimes does not remember important facts and procedures',
                1 => 'Does not easily understand; needs repeated instructions on the same tasks.',
            ],
        ],
        'work_attitude' => [
            'letter' => 'B',
            'name' => 'Work Attitude',
            'weight' => 15,
            'levels' => [
                5 => 'Possesses a positive perspective; always upbeat and ready to work; a pleasure to work with; always reports to work ahead of time without any absences and tardiness',
                4 => 'Accepts all work assignments; rarely complains; communicates well with superiors and coworkers; observes regularity and punctuality in attendance',
                3 => 'Takes setback in strides; pleasant and cooperative in most situations; reports to work on time with minimal absences or tardiness.',
                2 => 'Complains that many things are unfair, a whiner; reports to work but sometimes tardy and absent.',
                1 => 'Continually gripes about work assignments; disturbs others; uncooperative; temperamental; frequent absences or tardiness.',
            ],
        ],
        'conduct' => [
            'letter' => 'C',
            'name' => 'Conduct',
            'weight' => 10,
            'levels' => [
                5 => 'Always polite; careful not to offend anyone, maintains proper composure; makes special efforts to be helpful.',
                4 => 'Mostly polite and helpful; recognizes importance of human relationships; rarely losses temper.',
                3 => 'Observes common courtesy but doesn\'t always recognize opportunities and/or need to be polite or helpful.',
                2 => 'Harasses others; occasionally rude, foul language; not sensitive to others.',
                1 => 'Rude; ill mannered; uses obscene language; poor control of emotions; disrespectful to others.',
            ],
        ],
        'motivation_initiative' => [
            'letter' => 'D',
            'name' => 'Motivation/Initiative',
            'weight' => 10,
            'levels' => [
                5 => 'Curiosity goes beyond immediate job procedures; eager to learn more; works hard.',
                4 => 'Good knowledge of most procedures; anticipates next steps; goes beyond expectations and prepares ahead.',
                3 => 'Asks questions; has adequate degree of knowledge; does routine tasks without prompting; ready with tools.',
                2 => 'Does what is told; sometimes anticipates parts of the job; does no more than what is required; seems uninterested at times.',
                1 => 'Never anticipates requirements of any job or procedure; always wants or has to be told what to do; lacks initiatives, needs prodding.',
            ],
        ],
        'quality_accuracy_work' => [
            'letter' => 'E',
            'name' => 'Quality And Accuracy Of Work',
            'weight' => 20,
            'levels' => [
                5 => 'Work is very accurate; work meets or exceeds standards; takes pride in his/her work.',
                4 => 'Does good work; careful; makes very few mistakes; uses good judgment.',
                3 => 'Work usually meets standard or expectations; needs some extra supervision.',
                2 => 'Aims just to get by; careless; some job need to be reworked',
                1 => 'Makes frequent mistakes; wastes materials; lacks mechanical ability; needs constant supervision.',
            ],
        ],
        'quantity_of_work' => [
            'letter' => 'F',
            'name' => 'Quantity Of Work',
            'weight' => 10,
            'levels' => [
                5 => 'Highly productive; fast and accurate; when finished with assigned tasks finds others task to advance the job.',
                4 => 'Plans work well; works efficient; gets expected work on time.',
                3 => 'Does fair share; looks busy but after finishing a task usually doesn’t look for other tasks that need to be done.',
                2 => 'Does less than expected; does just enough to get by; requires additional oversight.',
                1 => 'Doesn’t plans work; slow; procedures very little; wastes time; continually visiting with others which slows down the work.',
            ],
        ],
        'safety_practices' => [
            'letter' => 'G',
            'name' => 'Safety Practices',
            'weight' => 15,
            'levels' => [
                5 => 'Always places safety first; helps others to be safe; does not take chances which might endangered self and others.',
                4 => 'Observes safety precautions; uses correct tools and wears appropriate safety apparel.',
                3 => 'Usually follows most safety procedures; usually does the job in safe manner.',
                2 => 'Careless, takes shortcuts which can create safety hazards.',
                1 => 'A hazard to self and others; potential for putting self and others at risk because of risky and unsafe attitude.',
            ],
        ],
        'appearance_hygiene' => [
            'letter' => 'H',
            'name' => 'Appearance/Hygiene',
            'weight' => 10,
            'levels' => [
                5 => 'Clothes ideal for work; Appearance and hygiene would be acceptable to costumers and co-trainees/personnel.',
                4 => 'Arrives at work with clothes clean; showered and clean; clothes are proper, protective and safe.',
                3 => 'Clothes usually clean and appropriate for job; grooming acceptable',
                2 => 'Poorly groomed; hair disheveled; clothing barely job appropriate and unkempt.',
                1 => 'Generally not presentable: clothes dirty and not appropriate for the job; looks shabby; unclean; smells bad at times.',
            ],
        ],
    ];

    /** Pull "rating_<key>" values out of a field-value lookup callable. */
    public static function ratingsFrom(callable $value): array
    {
        $ratings = [];
        foreach (array_keys(self::COMPETENCIES) as $key) {
            $ratings[$key] = $value('rating_'.$key);
        }

        return $ratings;
    }
}
