export class Parameter {
	static GROUP_ID = 200;

	constructor(fields: Object) {
		this.fields = fields;
	}

	id(): number {
		return +this.fields['id'];
	}

	type(): string {
		return this.fields['type'];
	}

	multiple(): boolean {
		return !!this.fields['multivalue'];
	}

	required(): boolean {
		return !!this.fields['required'];
	}

	allowCustom(): boolean {
		return !!this.fields['allowCustomValues'];
	}

	description(): string {
		return this.fields['description'];
	}

	name(): string {
		return this.fields['name'];
	}

	values(): Array {
		return this.fields['values'] ?? [];
	}

	maxLength(): string {
		return this.fields['constraints']?.maxLength;
	}

	minValue(): string {
		return this.fields['constraints']?.minValue;
	}

	maxValue(): string {
		return this.fields['constraints']?.maxValue;
	}

	valueRestrictions(): Array|null {
		return this.fields['valueRestrictions'];
	}

	showByDefault(): boolean {
		return this.required() && this.id() !== Parameter.GROUP_ID;
	}

	defaultUnit() : ?{id: number, name: string} {
		if (this.fields['unit'] == null) { return null; }

		for (const item of this.fields['unit']['units']) {
			if (this.fields['unit']['defaultUnitId'] === item.id) {
				return item;
			}
		}

		return null;
	}

	dependsOn(id: number) : boolean {
		const restrictions = this.valueRestrictions();

		if (restrictions == null) { return false; }

		for (const restriction of restrictions) {
			if (restriction['limitingParameterId'] === id) {
				return true;
			}
		}

		return false;
	}

	shownDependsOn(ids: number[]) : boolean {
		const restrictions = this.valueRestrictions();

		if (restrictions == null) { return true; }

		for (const restriction of restrictions) {
			if (ids.includes(restriction['limitingParameterId'])) {
				return true;
			}
		}

		return false;
	}
}