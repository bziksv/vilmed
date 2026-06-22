import {Parameter} from "./Parameter";

export class ParameterCollection {

	constructor(parameters: Array) {
		this.collection = parameters.map((fields) => new Parameter(fields));
	}

	count() : number {
		return this.collection.length;
	}

	all(): Parameter[] {
		return this.collection;
	}

	item(id: number) : ?Parameter {
		id = +id;

		for (const property of this.collection) {
			if (property.id() === id) {
				return property;
			}
		}

		return null;
	}

	dependentOf(id: number): Parameter[] {
		const result = [];

		for (const other of this.collection) {
			if (other.dependsOn(id)) {
				result.push(other);
			}
		}

		return result;
	}
}