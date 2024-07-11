import App from "./App";
import { createRoot } from '@wordpress/element';

import './styles/main.scss';

const container = document.getElementById('google-meet-and-zoom-integration');
const root = createRoot(container);
root.render(<App/>);
