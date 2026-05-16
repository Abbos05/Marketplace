import React, { useState, useEffect } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import Slider from '@/Components/Slider/Slider';
import Categories from '@/Components/Product/Categories';


export default function Category({
    auth,
    categoryData,
    showModal,
    homeSlides = [],
}) {
    return (
        <MainLayout auth={auth} categories={categoryData} showModal={showModal}>
            <Head title="Каталог" />
            <Categories categories={categoryData} />
            <Slider slides={homeSlides} />

        </MainLayout>
    );
}