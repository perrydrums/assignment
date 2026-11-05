import { Controller } from '@hotwired/stimulus';

/*
 * Prints the summary: the print stylesheet leaves out the tables, controls and drawers.
 */
export default class extends Controller {
    print() {
        window.print();
    }
}
