import { SVG, Path } from '@wordpress/primitives';

/** Purdue brand gold, matching the icon convention in purdue-home-theme. */
const BOILERMAKER_GOLD = '#cfb991';

const icon = (
	<SVG viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
		<Path
			fill={ BOILERMAKER_GOLD }
			d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7Zm12 7v10H5V9h14Z"
		/>
		<Path fill={ BOILERMAKER_GOLD } d="M7 11h4v4H7z" />
	</SVG>
);

export default icon;
