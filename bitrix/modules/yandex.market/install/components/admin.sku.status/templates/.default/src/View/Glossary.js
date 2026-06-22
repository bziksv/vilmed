import type {Locale} from "../Component/Locale";

export class Glossary {

	static rating(rating: ?number, locale: Locale) : string {
		return `<span class="ym-sku-status-rating level--${this.ratingLevel(rating)}">
			${rating != null ? `${rating} ${locale.message('RATING_SUFFIX')}` : '&mdash;'}
		</span>`;
	}

	static ratingLevel(rating: ?number): string {
		if (rating == null) {
			return 'unknown';
		}

		if (rating >= 80) {
			return 'good';
		}

		if (rating >= 40) {
			return 'warning';
		}

		return 'error';
	}

}