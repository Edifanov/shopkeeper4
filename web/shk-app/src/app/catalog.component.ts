import { Component, OnInit, Input } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { NgbModal, NgbActiveModal, NgbModalRef } from '@ng-bootstrap/ng-bootstrap';
import { FormGroup, FormBuilder, Validators } from '@angular/forms';
import { CategoriesService } from './services/categories.service';
import { Router, ActivatedRoute, Params } from '@angular/router';
import { ProductModalContent } from './product.component';
import { Category } from "./models/category.model";
import { Product } from "./models/product.model";
import { QueryOptions } from './models/query-options';
import { PageTableAbstractComponent, ModalContentAbstractComponent } from './page-table.abstract'
import * as _ from "lodash";
import { ProductsService } from "./services/products.service";

@Component({
    selector: 'shk-catalog',
    templateUrl: 'templates/page-catalog.html',
    providers: [ ProductsService ]
})
export class CatalogComponent extends PageTableAbstractComponent {

    title: string = 'Каталог';
    categories: Category[] = [];
    currentCategory: Category;

    constructor(
        dataService: ProductsService,
        activeModal: NgbActiveModal,
        modalService: NgbModal,
        titleService: Title
    ) {
        super(dataService, activeModal, modalService, titleService);
    }

    //TODO: get from settings
    //TODO: add field format option
    tableFields = [
        {
            name: 'id',
            title: 'ID',
            output_type: 'text'
        },
        {
            name: 'name',
            title: 'Системное имя',
            output_type: 'text'
        },
        {
            name: 'title',
            title: 'Название',
            output_type: 'text'
        },
        {
            name: 'price',
            title: 'Цена',
            output_type: 'number'
        },
        {
            name: 'published',
            title: 'Статус',
            output_type: 'boolean'
        }
    ];

    // constructor(
    //     public router: Router,
    //     private modalService: NgbModal,
    //     private titleService: Title,
    //     private categoriesService: CategoriesService,
    //     private productsService: ProductsService
    // ) {}

    // ngOnInit(): void {
    //     this.setTitle( this.title );
    // }

    getModalContent(){
        return ProductModalContent;
    }

    openCategory( category: Category ): void {
        this.currentCategory = _.clone( category );
        this.titleService.setTitle( this.title + ' / ' + this.currentCategory.title );
        this.getProducts();
    }

    openRootCategory(): void {
        this.currentCategory = new Category(0,0,'root', '','','');
        this.titleService.setTitle( this.title );
        this.getProducts();
    }

    getProducts(): void{
        // this.loading = true;
        // this.productsService.getList(this.currentCategory.id)
        //     .subscribe(
        //         items => {
        //             this.items = items;
        //             this.loading = false;
        //         },
        //         error =>  this.errorMessage = <any>error
        //     );
    }

    // modalProductOpen( itemId?: number ) {
    //     this.modalRef = this.modalService.open(ProductModalContent, {size: 'lg'});
    //     this.modalRef.componentInstance.modalTitle = 'Add product';
    //     this.modalRef.componentInstance.itemId = itemId || 0;
    //     this.modalRef.componentInstance.category = this.currentCategory;
    //     this.modalRef.result.then((result) => {
    //         if( result.reason && result.reason == 'edit' ){
    //
    //         } else {
    //             this.getProducts();
    //         }
    //     }, (reason) => {
    //
    //     });
    // }

    // public setTitle( newTitle: string ): void {
    //     this.titleService.setTitle( newTitle );
    // }
    //
    // selectAll( event ): void{
    //     this.selectedIds = [];
    //     if( event.target.checked ){
    //         for( let item of this.items ){
    //             this.selectedIds.push( item.id );
    //         }
    //     }
    // }

    // actionRequest(actionValue : [string, number]): void {
    //     switch(actionValue[0]){
    //         case 'edit':
    //             this.modalProductOpen(actionValue[1]);
    //             break;
    //         case 'copy':
    //             //this.modalProductOpen(actionValue[1], true);
    //             break;
    //         case 'delete':
    //             //this.deleteItemConfirm(actionValue[1]);
    //             break;
    //     }
    // }

}
