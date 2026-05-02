import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function DeleteUserForm({ className = '' }) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);

    const {
        delete: destroy,
        processing,
    } = useForm({});

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser = (e) => {
        e.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);
    };

    return (
        <section className={`space-ys-6 ${className}`}>

            <form onSubmit={deleteUser} className="p-6a ">

                <DangerButton disabled={processing}>
                    
                    Удалить
                </DangerButton>
            </form>
        </section>
    );
}