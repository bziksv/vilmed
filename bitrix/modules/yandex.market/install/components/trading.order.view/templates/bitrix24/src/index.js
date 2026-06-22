import Factory from "./factory";
import Box from "./box/field";
import BasketConfirm from "./basketconfirm/field";
import Property from "./property/field";
import Print from "./print/field";
import Notification from "./notification/field";
import Editor from "./compatible/editor";
import './common.css';

// factory

const factory = new Factory({
	map: {
		notification: Notification,
		box: Box,
		basket_confirm: BasketConfirm,
		property: Property,
		print: Print,
	},
});

factory.register();

// compatible

const editor = new Editor();

editor.start();

export {
	Notification,
	BasketConfirm,
	Box,
	Property,
	Print,
};