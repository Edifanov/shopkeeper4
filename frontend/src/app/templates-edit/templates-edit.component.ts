import {Component, ElementRef, OnInit, ViewChild} from '@angular/core';

import {findIndex} from 'lodash';
import {TemplatesEditService} from './services/templates-edit.service';
import {NgbActiveModal, NgbModal} from '@ng-bootstrap/ng-bootstrap';
import {TranslateService} from '@ngx-translate/core';

import {PageTableAbstractComponent} from '../page-table.abstract';
import {Template} from './models/template.model';
import {QueryOptions} from '../models/query-options';
import {ModalTemplateEditComponent} from './modal-template.component';
import {FileRegularInterface} from './models/file-regular.interface';

@Component({
    selector: 'app-template-edit',
    templateUrl: './templates/templates-edit.component.html',
    styleUrls: ['./templates-edit.component.css'],
    providers: [TemplatesEditService]
})
export class TemplatesEditComponent extends PageTableAbstractComponent<Template> {

    static title = 'TEMPLATES';
    @ViewChild('dropdownConfig', { static: false }) dropdownConfig: ElementRef;
    @ViewChild('dropdownStyles', { static: false }) dropdownStyles: ElementRef;
    @ViewChild('dropdownScripts', { static: false }) dropdownScripts: ElementRef;
    queryOptions: QueryOptions = new QueryOptions('path', 'asc', 1, 10, 0, 0);

    tableFields = [
        {
            name: 'name',
            sortName: 'name',
            title: 'NAME',
            outputType: 'text',
            outputProperties: {}
        },
        {
            name: 'themeName',
            sortName: 'themeName',
            title: 'TEMPLATE_THEME_NAME',
            outputType: 'text',
            outputProperties: {}
        },
        {
            name: 'path',
            sortName: 'path',
            title: 'PATH',
            outputType: 'text',
            outputProperties: {}
        }
    ];
    files: {[key: string]: FileRegularInterface[]} = {config: [], css: [], js: []};

    constructor(
        public dataService: TemplatesEditService,
        public activeModal: NgbActiveModal,
        public modalService: NgbModal,
        public translateService: TranslateService
    ) {
        super(dataService, activeModal, modalService, translateService);
    }

    afterInit(): void {
        this.getEditableFiles();
    }

    getEditableFiles(): void {
        this.dataService.getEditableFiles()
            .subscribe((res) => {
                if (res.items) {
                    this.files['css'] = res.items.filter((item) => {
                        return item.type === 'css';
                    });
                    this.files['js'] = res.items.filter((item) => {
                        return item.type === 'js';
                    });
                    this.files['config'] = res.items.filter((item) => {
                        return item.type === 'config';
                    });
                }
            });
    }

    getModalContent() {
        return ModalTemplateEditComponent;
    }

    setModalInputs(itemId?: number, isItemCopy: boolean = false): void {
        const templateIndex = findIndex(this.items, {id: itemId});
        const template = templateIndex > -1 ? this.items[templateIndex] : null;
        const isEditMode = template && template.id > -1;
        this.modalRef.componentInstance.modalTitle = isEditMode
            ? this.getLangString('EDITING')
            : this.getLangString('ADD');
        this.modalRef.componentInstance['isItemCopy'] = isItemCopy || false;
        this.modalRef.componentInstance['isEditMode'] = isEditMode;
        this.modalRef.componentInstance['template'] = template;
    }

    deleteSelected() {
        if (this.selectedIds.length === 0) {
            this.showAlert(this.getLangString('NOTHING_IS_SELECTED'));
            return;
        }
        const pathArr = [];
        this.selectedIds.forEach((id) => {
            const templateIndex = findIndex(this.items, {id: id});
            if (templateIndex > -1) {
                const template = templateIndex > -1 ? this.items[templateIndex] : null;
                pathArr.push(Template.getPath(template));
            }
        });
        this.confirmAction(this.getLangString('YOU_SURE_YOU_WANT_DELETE_SELECTED'))
            .then((result) => {
                if (result === 'accept') {
                    this.dataService.deleteFilesBatch(pathArr)
                        .subscribe(res => {
                                this.clearSelected();
                                this.getList();
                            },
                            err => this.showAlert(err.error || this.getLangString('ERROR')));
                }
            });
    }

    deleteItem(itemId: number): void {
        const templateIndex = findIndex(this.items, {id: itemId});
        const template = templateIndex > -1 ? this.items[templateIndex] : null;
        if (!template) {
            return;
        }
        const templatePath = Template.getPath(template);
        this.confirmAction(this.getLangString('YOU_SURE_YOU_WANT_DELETE'))
            .then((result) => {
                if (result === 'accept') {
                    this.dataService.deleteTemplateItem(templatePath)
                        .subscribe(() => {
                            this.getList();
                        }, (err) => {
                            if (err['error']) {
                                this.showAlert(err['error']);
                            }
                        });
                }
            });
    }

    editFile(file: FileRegularInterface, event?: MouseEvent): void {
        if (event) {
            event.preventDefault();
        }
        this.modalRef = this.modalService.open(ModalTemplateEditComponent, {
            size: 'lg',
            backdrop: 'static',
            keyboard: false
        });
        this.modalRef.componentInstance.modalTitle = this.getLangString('EDITING') + ` ${file.name}`;
        this.modalRef.componentInstance['isItemCopy'] = false;
        this.modalRef.componentInstance['isEditMode'] = true;
        this.modalRef.componentInstance['file'] = file;
        this.modalRef.result.then((result) => {
            this.getEditableFiles();
        }, (reason) => {
            // console.log( 'reason', reason );
        });
    }

}
