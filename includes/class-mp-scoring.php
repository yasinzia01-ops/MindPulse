<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple point-total -> band scoring engine.
 *
 * Each submitted answer is `{ question_id: option_index }`. Every option
 * carries a point value; the sum of selected options' points is the
 * total score, which is matched against admin-defined score bands to
 * find the resulting profile.
 */
class MP_Scoring {

	/**
	 * @param array $quiz_data Decoded quiz data (see MP_CPT::get_quiz_data()).
	 * @param array $answers   Map of question_id => option_index.
	 * @return array{total_score:int,band:array|null}
	 */
	public static function score( array $quiz_data, array $answers ) {
		$total = 0;

		foreach ( $quiz_data['questions'] as $question ) {
			$qid = $question['id'];
			if ( ! isset( $answers[ $qid ] ) ) {
				continue;
			}

			$option_index = (int) $answers[ $qid ];
			if ( isset( $question['options'][ $option_index ]['points'] ) ) {
				$total += (int) $question['options'][ $option_index ]['points'];
			}
		}

		$band = self::match_band( $quiz_data['bands'], $total );

		return array(
			'total_score' => $total,
			'band'        => $band,
		);
	}

	/**
	 * Finds the first band whose [min,max] range contains the score.
	 */
	public static function match_band( array $bands, $score ) {
		foreach ( $bands as $band ) {
			$min = isset( $band['min'] ) ? (int) $band['min'] : PHP_INT_MIN;
			$max = isset( $band['max'] ) ? (int) $band['max'] : PHP_INT_MAX;

			if ( $score >= $min && $score <= $max ) {
				return $band;
			}
		}

		return null;
	}
}
