import { createApp } from "vue";
import App from "./App.vue";
import CookieConsentVue from "./plugins/cookieconsentvue.js";

const settings = window.DowleyCookieConsent;

if (settings.enabled) {
	const cookieDiv = await createCookieDiv();

	createCookieApp();
}

async function createCookieDiv() {
	const cookieDiv = document.createElement("div");
	cookieDiv.setAttribute("id", "dowleycookieconsent");
	document.body.appendChild(cookieDiv);

	return true;
}

function createCookieApp() {
	const props = {
		triggerPosition: settings.triggerPosition,
		consentMode: settings.sendGtag,
		colours: settings.colours,
		icon: settings.cookieIcon,
	};

	createApp(App, props).use(CookieConsentVue).mount("#dowleycookieconsent");
}
