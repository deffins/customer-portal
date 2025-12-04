<?php
/**
 * Surveys Module - Definitions and Scoring Logic
 */

if (!defined('ABSPATH')) exit;

class CP_Surveys {

    /**
     * Get all available surveys
     */
    public function get_available_surveys() {
        return array(
            'liver_short_v1' => array(
                'id' => 'liver_short_v1',
                'title' => 'Aknu veselības anketa (īsa versija)',
                'description' => 'Īsa anketa par aknu, žults, gremošanas un stresa simptomiem.'
            )
        );
    }

    /**
     * Get survey definition by ID
     */
    public function get_survey_definition($survey_id) {
        if ($survey_id === 'liver_short_v1') {
            return $this->get_liver_short_v1_definition();
        }
        return null;
    }

    /**
     * Liver Health Short Survey Definition
     */
    private function get_liver_short_v1_definition() {
        return array(
            'id' => 'liver_short_v1',
            'title' => 'Aknu veselības anketa (īsa versija)',
            'description' => 'Īsa anketa par aknu, žults, gremošanas un stresa simptomiem.',
            'dimensions' => array(
                'bile' => 'Žults / Gremošana',
                'detox' => 'Detoks / Toksiska slodze',
                'gut' => 'Zarnu mikrobiota / Disbioze',
                'hormones' => 'Hormoni / Steroīdu slodze',
                'ans' => 'Autonomā nervu sistēma / Stress',
                'inflammation' => 'Iekaisums / Autoimūns profils',
                'alcohol' => 'Alkohola tolerance'
            ),
            'questions' => array(
                array(
                    'id' => 'q1',
                    'type' => 'slider',
                    'dimension' => 'bile',
                    'label' => 'Cik bieži jūti smagumu vai spiedienu labajā paribē?',
                    'min' => 0,
                    'max' => 10
                ),
                array(
                    'id' => 'q2',
                    'type' => 'single_choice',
                    'dimension' => 'bile',
                    'label' => 'Vai pēc trekniem ēdieniem kļūst slikti, smagi vai rodas nelabums?',
                    'options' => array(
                        array('value' => 'never', 'label' => 'Nekad', 'score' => 0),
                        array('value' => 'rarely', 'label' => 'Reizēm', 'score' => 1),
                        array('value' => 'often', 'label' => 'Bieži', 'score' => 2),
                        array('value' => 'always', 'label' => 'Gandrīz vienmēr', 'score' => 3)
                    )
                ),
                array(
                    'id' => 'q3',
                    'type' => 'single_choice',
                    'dimension' => 'gut',
                    'label' => 'Vai bieži ir uzpūšanās, pat ja ēdiens nav smags?',
                    'options' => array(
                        array('value' => 'never', 'label' => 'Nekad', 'score' => 0),
                        array('value' => 'rarely', 'label' => 'Reizēm', 'score' => 1),
                        array('value' => 'often', 'label' => 'Bieži', 'score' => 2),
                        array('value' => 'always', 'label' => 'Gandrīz vienmēr', 'score' => 3)
                    )
                ),
                array(
                    'id' => 'q4',
                    'type' => 'slider',
                    'dimension' => 'detox',
                    'label' => 'Cik bieži pēc ēšanas parādās nogurums vai miegainība?',
                    'min' => 0,
                    'max' => 10
                ),
                array(
                    'id' => 'q5',
                    'type' => 'single_choice',
                    'dimension' => 'detox',
                    'label' => 'Vai tev ir jūtība pret smaržām, ķīmiju, kosmētiku vai dūmiem?',
                    'options' => array(
                        array('value' => 'never', 'label' => 'Nekad', 'score' => 0),
                        array('value' => 'rarely', 'label' => 'Reizēm', 'score' => 1),
                        array('value' => 'often', 'label' => 'Bieži', 'score' => 2),
                        array('value' => 'always', 'label' => 'Gandrīz vienmēr', 'score' => 3)
                    )
                ),
                array(
                    'id' => 'q6',
                    'type' => 'single_choice',
                    'dimension' => 'alcohol',
                    'label' => 'Kā tu panes alkoholu?',
                    'options' => array(
                        array('value' => 'normal', 'label' => 'Normāli', 'score' => 0),
                        array('value' => 'sensitive', 'label' => 'Jūtu stipri', 'score' => 1),
                        array('value' => 'tired_headache', 'label' => 'Ļoti ātri sagurstu vai sāp galva', 'score' => 2),
                        array('value' => 'very_poor', 'label' => 'Pat mazas devas izraisa sliktu sajūtu', 'score' => 3)
                    )
                ),
                array(
                    'id' => 'q7',
                    'type' => 'single_choice',
                    'dimension' => 'inflammation',
                    'label' => 'Vai pēdējā laikā āda kļūst jutīga, niez vai parādās apsārtumi?',
                    'options' => array(
                        array('value' => 'never', 'label' => 'Nekad', 'score' => 0),
                        array('value' => 'rarely', 'label' => 'Reizēm', 'score' => 1),
                        array('value' => 'often', 'label' => 'Bieži', 'score' => 2),
                        array('value' => 'always', 'label' => 'Gandrīz vienmēr', 'score' => 3)
                    )
                ),
                array(
                    'id' => 'q8',
                    'type' => 'slider',
                    'dimension' => 'detox',
                    'label' => 'Cik bieži ir galvassāpes, kas šķiet saistītas ar ēšanu vai gremošanu?',
                    'min' => 0,
                    'max' => 10
                ),
                array(
                    'id' => 'q9',
                    'type' => 'single_choice',
                    'dimension' => 'bile',
                    'label' => 'Vai bieži parādās rūgta garša mutē, īpaši no rīta?',
                    'options' => array(
                        array('value' => 'never', 'label' => 'Nekad', 'score' => 0),
                        array('value' => 'rarely', 'label' => 'Reizēm', 'score' => 1),
                        array('value' => 'often', 'label' => 'Bieži', 'score' => 2),
                        array('value' => 'always', 'label' => 'Gandrīz vienmēr', 'score' => 3)
                    )
                ),
                array(
                    'id' => 'q10',
                    'type' => 'slider',
                    'dimension' => 'ans',
                    'label' => 'Kāds ir tavs pašreizējais stresa līmenis ikdienā?',
                    'min' => 0,
                    'max' => 10
                ),
                array(
                    'id' => 'q11',
                    'type' => 'single_choice',
                    'dimension' => 'gut',
                    'label' => 'Vai tava vēdera izeja bieži mainās (aizcietējums, caureja, nepietiekama iztukšošanās)?',
                    'options' => array(
                        array('value' => 'never', 'label' => 'Nekad', 'score' => 0),
                        array('value' => 'rarely', 'label' => 'Reizēm', 'score' => 1),
                        array('value' => 'often', 'label' => 'Bieži', 'score' => 2),
                        array('value' => 'always', 'label' => 'Gandrīz vienmēr', 'score' => 3)
                    )
                ),
                array(
                    'id' => 'q12',
                    'type' => 'text',
                    'dimension' => null,
                    'label' => 'Kas, tavuprāt, šobrīd visvairāk traucē tavai veselībai?'
                ),
                array(
                    'id' => 'q13',
                    'type' => 'single_choice',
                    'dimension' => 'bile',
                    'label' => 'Kādā krāsā visbiežāk ir tava vēdera izeja?',
                    'options' => array(
                        array('value' => 'brown_normal', 'label' => 'Vidēji brūna (normāla)', 'base_score' => 0),
                        array('value' => 'light_brown', 'label' => 'Gaiši brūna', 'base_score' => 1),
                        array('value' => 'yellow', 'label' => 'Dzeltenīga', 'base_score' => 2),
                        array('value' => 'clay', 'label' => 'Pelēcīga / mālaina', 'base_score' => 3),
                        array('value' => 'green', 'label' => 'Zaļgana', 'base_score' => 1),
                        array('value' => 'dark_brown', 'label' => 'Tumši brūna / gandrīz melna', 'base_score' => 2)
                    )
                ),
                array(
                    'id' => 'q14',
                    'type' => 'single_choice',
                    'dimension' => 'bile',
                    'label' => 'Cik bieži šī krāsa ir?',
                    'options' => array(
                        array('value' => 'rare', 'label' => 'Reti', 'freq_score' => 0),
                        array('value' => 'sometimes', 'label' => 'Reizēm', 'freq_score' => 1),
                        array('value' => 'often', 'label' => 'Bieži', 'freq_score' => 2),
                        array('value' => 'always', 'label' => 'Gandrīz vienmēr', 'freq_score' => 3)
                    )
                ),
                array(
                    'id' => 'q15',
                    'type' => 'single_choice',
                    'dimension' => 'bile',
                    'label' => 'Vai fēcēm ir eļļaina plēvīte vai tās \'peld\' ūdenī?',
                    'options' => array(
                        array('value' => 'never', 'label' => 'Nekad', 'base_score' => 0),
                        array('value' => 'rarely', 'label' => 'Reizēm redzu eļļainu plēvīti', 'base_score' => 1),
                        array('value' => 'often', 'label' => 'Bieži redzu taukainas svītras / plēvi', 'base_score' => 2),
                        array('value' => 'always', 'label' => 'Gandrīz vienmēr – fēces peld, ūdenī paliek plēve', 'base_score' => 3)
                    )
                ),
                array(
                    'id' => 'q16',
                    'type' => 'single_choice',
                    'dimension' => 'bile',
                    'label' => 'Cik bieži tas notiek?',
                    'options' => array(
                        array('value' => 'rare', 'label' => 'Reti', 'freq_score' => 0),
                        array('value' => 'sometimes', 'label' => 'Reizēm', 'freq_score' => 1),
                        array('value' => 'often', 'label' => 'Bieži', 'freq_score' => 2),
                        array('value' => 'always', 'label' => 'Gandrīz vienmēr', 'freq_score' => 3)
                    )
                )
            )
        );
    }

    /**
     * Calculate scores from survey answers
     *
     * @param string $survey_id Survey identifier
     * @param array $answers Associative array of question_id => answer
     * @return array Array with total_score and dimension_scores
     */
    public function calculate_scores($survey_id, $answers) {
        $survey = $this->get_survey_definition($survey_id);
        if (!$survey) {
            return array('total_score' => 0, 'dimension_scores' => array());
        }

        $dimension_scores = array();
        $total_score = 0;

        // Initialize all dimensions to 0
        foreach ($survey['dimensions'] as $dim_key => $dim_label) {
            $dimension_scores[$dim_key] = 0;
        }

        foreach ($survey['questions'] as $question) {
            $q_id = $question['id'];
            $q_type = $question['type'];
            $dimension = $question['dimension'];

            if (!isset($answers[$q_id])) {
                continue; // Skip unanswered questions
            }

            $answer = $answers[$q_id];
            $question_score = 0;

            // Calculate score based on question type
            if ($q_type === 'slider') {
                $question_score = intval($answer);
            } elseif ($q_type === 'single_choice') {
                // Find the selected option and get its score
                if (isset($question['options'])) {
                    foreach ($question['options'] as $option) {
                        if ($option['value'] === $answer) {
                            if (isset($option['score'])) {
                                $question_score = intval($option['score']);
                            }
                            break;
                        }
                    }
                }
            } elseif ($q_type === 'text') {
                // Text answers don't contribute to score
                $question_score = 0;
            }

            // Add to dimension score if dimension is set
            if ($dimension && isset($dimension_scores[$dimension])) {
                $dimension_scores[$dimension] += $question_score;
            }

            $total_score += $question_score;
        }

        // Special handling for q13+q14 (color + frequency)
        if (isset($answers['q13']) && isset($answers['q14'])) {
            $base_score = 0;
            $freq_score = 0;

            // Get base_score from q13
            $q13_def = null;
            foreach ($survey['questions'] as $q) {
                if ($q['id'] === 'q13') {
                    $q13_def = $q;
                    break;
                }
            }
            if ($q13_def && isset($q13_def['options'])) {
                foreach ($q13_def['options'] as $option) {
                    if ($option['value'] === $answers['q13']) {
                        $base_score = isset($option['base_score']) ? intval($option['base_score']) : 0;
                        break;
                    }
                }
            }

            // Get freq_score from q14
            $q14_def = null;
            foreach ($survey['questions'] as $q) {
                if ($q['id'] === 'q14') {
                    $q14_def = $q;
                    break;
                }
            }
            if ($q14_def && isset($q14_def['options'])) {
                foreach ($q14_def['options'] as $option) {
                    if ($option['value'] === $answers['q14']) {
                        $freq_score = isset($option['freq_score']) ? intval($option['freq_score']) : 0;
                        break;
                    }
                }
            }

            // Calculate color subscore: base_score * max(1, freq_score)
            $color_subscore = $base_score * max(1, $freq_score);
            $dimension_scores['bile'] += $color_subscore;
            $total_score += $color_subscore;
        }

        // Special handling for q15+q16 (oiliness + frequency)
        if (isset($answers['q15']) && isset($answers['q16'])) {
            $base_score = 0;
            $freq_score = 0;

            // Get base_score from q15
            $q15_def = null;
            foreach ($survey['questions'] as $q) {
                if ($q['id'] === 'q15') {
                    $q15_def = $q;
                    break;
                }
            }
            if ($q15_def && isset($q15_def['options'])) {
                foreach ($q15_def['options'] as $option) {
                    if ($option['value'] === $answers['q15']) {
                        $base_score = isset($option['base_score']) ? intval($option['base_score']) : 0;
                        break;
                    }
                }
            }

            // Get freq_score from q16
            $q16_def = null;
            foreach ($survey['questions'] as $q) {
                if ($q['id'] === 'q16') {
                    $q16_def = $q;
                    break;
                }
            }
            if ($q16_def && isset($q16_def['options'])) {
                foreach ($q16_def['options'] as $option) {
                    if ($option['value'] === $answers['q16']) {
                        $freq_score = isset($option['freq_score']) ? intval($option['freq_score']) : 0;
                        break;
                    }
                }
            }

            // Calculate fat subscore: base_score * max(1, freq_score)
            $fat_subscore = $base_score * max(1, $freq_score);
            $dimension_scores['bile'] += $fat_subscore;
            $total_score += $fat_subscore;
        }

        return array(
            'total_score' => $total_score,
            'dimension_scores' => $dimension_scores
        );
    }

    /**
     * Get interpretation of total score
     */
    public function get_score_interpretation($total_score) {
        if ($total_score <= 15) {
            return array(
                'level' => 'low',
                'label' => 'Zema varbūtība',
                'description' => 'Zema varbūtība nozīmīgām aknu/žults problēmām'
            );
        } elseif ($total_score <= 30) {
            return array(
                'level' => 'mild',
                'label' => 'Viegla stagnācija',
                'description' => 'Viegla stagnācija / pārslodze'
            );
        } elseif ($total_score <= 45) {
            return array(
                'level' => 'moderate',
                'label' => 'Vidēja problēma',
                'description' => 'Vidēja problēma, ieteicams dziļāk pētīt'
            );
        } else {
            return array(
                'level' => 'high',
                'label' => 'Augsts risks',
                'description' => 'Augsts risks / spēcīga indikācija dziļākam darbam'
            );
        }
    }
}
