import { Component, OnInit, Input } from '@angular/core';
import { NgbModal, NgbActiveModal, NgbModalRef } from '@ng-bootstrap/ng-bootstrap';
import { Observable } from 'rxjs/Observable';
import * as _ from "lodash";

import { Category } from "./models/category.model";
import { ProductsService } from "./services/products.service";
import { PageTableAbstractComponent } from './page-table.abstract'
import { ProductModalContent } from './product.component';
import { ContentType } from './models/content_type.model';
import { ContentTypesService } from './services/content_types.service';
import { Product } from './models/product.model';

@Component({
    selector: 'catalog-category',
    templateUrl: 'templates/catalog-category.html',
    providers: [ProductsService]
})
export class CatalogCategoryComponent extends PageTableAbstractComponent<Product> {
    static title = 'CATEGORY';

    currentCategory: Category;
    currentContentType: ContentType;
    tableFields = [];

    constructor(
        public dataService: ProductsService,
        public activeModal: NgbActiveModal,
        public modalService: NgbModal,
        private contentTypesService: ContentTypesService
    ) {
        super(dataService, activeModal, modalService);
    }

    ngOnInit(): void {

    }

    updateTableConfig(): void {
        if(!this.currentContentType){
            return;
        }
        this.tableFields = [
            {
                name: 'id',
                sortName: 'id',
                title: 'ID',
                outputType: 'number',
                outputProperties: {}
            }
        ];
        this.currentContentType.fields.forEach((field) => {
            if (field.showInTable) {
                this.tableFields.push({
                    name: field.name,
                    sortName: field.name,
                    title: field.title,
                    outputType: field.outputType,
                    outputProperties: field.outputProperties
                });
            }
        });
    }

    getModalContent() {
        return ProductModalContent;
    }

    getContentType(): Observable<ContentType> {
        return this.contentTypesService
            .getItemByName(this.currentCategory.contentTypeName);
    }

    openCategory(category: Category): void {
        this.currentCategory = _.clone(category);
        if(!this.currentCategory.contentTypeName){
            this.items = [];
            this.tableFields = [];
            this.currentCategory.id = 0;
            return;
        }

        this.dataService.setRequestUrl('products/' + this.currentCategory.id);
        this.loading = true;
        this.getContentType()
            .subscribe((data) => {
                this.currentContentType = data;
                this.loading = false;
                this.updateTableConfig();
                this.getList();
            }, () => {
                this.items = [];
                this.tableFields = [];
                this.currentCategory.id = 0;
            });
    }

    openRootCategory(): void {
        this.currentCategory = new Category(0, false, 0, 'root', '', '', '', true);
        this.dataService.setRequestUrl('products/' + this.currentCategory.id);
        this.getList();
    }

    setModalInputs(itemId?: number, isItemCopy: boolean = false): void {
        PageTableAbstractComponent.prototype.setModalInputs.call(this, itemId, isItemCopy);
        this.modalRef.componentInstance.category = this.currentCategory;
    }

}
